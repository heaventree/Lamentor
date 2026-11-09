<?php

namespace Lamentor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Lamentor\LamentorServiceProvider;
use Lamentor\Models\Document;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\ElementTreeValidator;
use Lamentor\Templates\TemplateFormat;
use Orchestra\Testbench\TestCase;

class LaravelLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('lamentor.middleware', ['web']);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
    }

    private function tree(string $title = 'Hello'): array
    {
        return [[
            'id' => 'c0ffee1', 'elType' => 'container', 'settings' => [], 'isInner' => false, 'elements' => [
                ['id' => 'abc1234', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => $title], 'elements' => []],
            ],
        ]];
    }

    public function test_document_model_renders(): void
    {
        $doc = Document::create(['title' => 'T', 'data' => $this->tree('Model title')]);

        $rendered = $doc->render();

        $this->assertStringContainsString('Model title', $rendered->html);
        $this->assertStringContainsString('data-elementor-id="' . $doc->id . '"', $rendered->html);
    }

    public function test_blade_component_outputs_links_style_and_markup(): void
    {
        $doc = Document::create(['title' => 'T', 'data' => $this->tree('From blade')]);

        $html = Blade::render('<x-lamentor::document :document="$doc" />', ['doc' => $doc]);

        $this->assertStringContainsString('/vendor/lamentor/elementor/assets/css/frontend.min.css', $html);
        $this->assertStringContainsString('From blade', $html);
        $this->assertStringContainsString('<style>', $html);
    }

    public function test_style_element_cannot_be_closed_by_saved_values(): void
    {
        $tree = $this->tree();
        $tree[0]['elements'][0]['settings']['title_color'] = 'red}</style><script>alert(1)</script>';
        $doc = Document::create(['title' => 'T', 'data' => $tree]);

        $html = Blade::render('<x-lamentor::document :document="$doc" />', ['doc' => $doc]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame(1, substr_count($html, '</style>'));
    }

    public function test_css_values_cannot_break_out_of_the_rule(): void
    {
        $tree = $this->tree();
        $tree[0]['elements'][0]['settings']['title_color'] = 'red; } body { display:none';
        $css = app(Renderer::class)->document($tree, 1)->css;

        $this->assertStringNotContainsString('body{', $css);
        // Braces and semicolons were stripped, so the text stays inside the one declaration.
        $this->assertSame(substr_count($css, '{'), substr_count($css, '}'));
        $this->assertStringContainsString('color:red  body  display:none;}', $css);
    }

    public function test_save_content_keeps_revisions_and_prunes(): void
    {
        config(['lamentor.max_revisions' => 2]);
        $doc = Document::create(['title' => 'T', 'data' => $this->tree('v0')]);

        foreach (['v1', 'v2', 'v3', 'v4'] as $v) {
            $doc->saveContent($this->tree($v));
        }

        $this->assertSame(2, $doc->revisions()->count());
        $this->assertSame('v4', $doc->fresh()->data[0]['elements'][0]['settings']['title']);
        $this->assertSame('v3', $doc->revisions()->orderByDesc('id')->first()->data[0]['elements'][0]['settings']['title']);
    }

    public function test_validator_rejects_bad_trees(): void
    {
        $v = new ElementTreeValidator(maxDepth: 3, maxNodes: 5);

        $this->assertSame([], $v->errors($this->tree()));
        $this->assertNotEmpty($v->errors('nope'));
        $this->assertNotEmpty($v->errors([['id' => 'x', 'elType' => 'script', 'settings' => []]]));
        $this->assertNotEmpty($v->errors([['id' => 'bad id!', 'elType' => 'container']]));
        $this->assertNotEmpty($v->errors([['id' => 'w', 'elType' => 'widget']]));

        $deep = ['id' => 'a', 'elType' => 'container', 'elements' => []];
        $node = &$deep;
        for ($i = 0; $i < 5; $i++) {
            $node['elements'][] = ['id' => 'n' . $i, 'elType' => 'container', 'elements' => []];
            $node = &$node['elements'][0];
        }
        $this->assertNotEmpty($v->errors([$deep]));
    }

    public function test_template_import_regenerates_ids_and_round_trips_export(): void
    {
        $template = TemplateFormat::parse(json_encode(['title' => 'Tpl', 'type' => 'page', 'content' => $this->tree('Imported'), 'page_settings' => []]));

        $this->assertNotSame('c0ffee1', $template['content'][0]['id']);
        $this->assertSame(7, strlen($template['content'][0]['elements'][0]['id']));
        $this->assertSame('Imported', $template['content'][0]['elements'][0]['settings']['title']);

        $export = TemplateFormat::export('Tpl', 'page', $template['content']);
        $this->assertSame('0.4', $export['version']);

        $bare = TemplateFormat::parse(json_encode($this->tree()));
        $this->assertCount(1, $bare['content']);

        $this->expectException(\InvalidArgumentException::class);
        TemplateFormat::parse('{"foo":1}');
    }

    public function test_api_crud_validation_and_import(): void
    {
        $id = $this->postJson('/lamentor/api/documents', ['title' => 'API doc'])->assertCreated()->json('id');

        $this->putJson("/lamentor/api/documents/{$id}", ['data' => $this->tree('Saved')])->assertOk();
        $this->assertSame('Saved', Document::find($id)->data[0]['elements'][0]['settings']['title']);

        $this->putJson("/lamentor/api/documents/{$id}", ['data' => [['id' => 'x', 'elType' => 'nope']]])->assertStatus(422);

        $this->getJson("/lamentor/api/documents/{$id}/export")->assertOk()->assertJsonPath('version', '0.4');

        $this->postJson('/lamentor/api/documents/import', ['template' => ['title' => 'Imp', 'content' => $this->tree('Imp')]])
            ->assertCreated()->assertJsonPath('title', 'Imp');
        $this->postJson('/lamentor/api/documents/import', ['template' => ['nothing' => true]])->assertStatus(422);

        $this->get("/lamentor/preview/{$id}")->assertOk()->assertSee('Saved', false);
        $this->deleteJson("/lamentor/api/documents/{$id}")->assertNoContent();
    }

    public function test_schema_and_render_endpoints(): void
    {
        $widgets = $this->getJson('/lamentor/api/schema/widgets')->assertOk()->json();
        $this->assertContains('heading', array_column($widgets, 'name'));

        $this->getJson('/lamentor/api/schema/controls/heading')->assertOk()->assertJsonStructure(['type', 'controls', 'defaults']);
        $this->getJson('/lamentor/api/schema/controls/container')->assertOk();
        $this->getJson('/lamentor/api/schema/controls/nope')->assertNotFound();
        $this->getJson('/lamentor/api/kit')->assertOk()->assertJsonPath('system_colors.0._id', 'primary');

        $this->postJson('/lamentor/api/render', ['elements' => $this->tree('Live')])
            ->assertOk()->assertJsonPath('stylesheets.0', 'css/frontend.min.css');
        $this->postJson('/lamentor/api/render', ['elements' => [['id' => 'x', 'elType' => 'bad']]])->assertStatus(422);
    }

    public function test_gate_can_restrict_editing(): void
    {
        Gate::define('lamentor.edit', fn ($user = null) => false);

        $this->getJson('/lamentor/api/documents')->assertForbidden();
    }
}
