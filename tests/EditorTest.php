<?php

namespace Lamentor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lamentor\LamentorServiceProvider;
use Lamentor\Models\Document;
use Lamentor\Rendering\Renderer;
use Lamentor\Widgets\WidgetRegistry;
use Orchestra\Testbench\TestCase;

class EditorTest extends TestCase
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

    public function test_editor_page_boots_with_document_data_and_script_safe_json(): void
    {
        $doc = Document::create(['title' => 'Edit me </script><b>', 'data' => [[
            'id' => 'a1', 'elType' => 'container', 'settings' => [], 'elements' => [], 'isInner' => false,
        ]]]);

        $html = $this->get("/lamentor/editor/{$doc->id}")->assertOk()->getContent();

        $this->assertStringContainsString('window.LAMENTOR', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('/lamentor/editor-assets/editor.js', $html);
        $this->assertStringNotContainsString('</script><b>', $html, 'a title cannot close the inline script');
    }

    public function test_editor_assets_are_served_with_etag_revalidation(): void
    {
        $first = $this->get('/lamentor/editor-assets/editor.js')->assertOk();
        $first->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->get('/lamentor/editor-assets/editor.js', ['If-None-Match' => $etag])->assertStatus(304);
        $this->get('/lamentor/editor-assets/controls.js')->assertOk();
        $this->get('/lamentor/editor-assets/editor.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->get('/lamentor/editor-assets/../../composer.json')->assertNotFound();
        $this->get('/lamentor/editor-assets/secret.php')->assertNotFound();
    }

    public function test_media_upload_accepts_images_and_rejects_svg_and_scripts(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        // A real 1x1 PNG (the GD extension needed by fake()->image() may be missing).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $ok = $this->postJson('/lamentor/api/media', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png', $png)]);
        $ok->assertCreated()->assertJsonStructure(['url', 'id', 'alt', 'source']);

        $svg = \Illuminate\Http\UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>');
        $this->postJson('/lamentor/api/media', ['file' => $svg])->assertStatus(422);

        $php = \Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');
        $this->postJson('/lamentor/api/media', ['file' => $php])->assertStatus(422);
    }

    public function test_every_widget_schema_endpoint_returns_controls_and_defaults(): void
    {
        $names = $this->app->make(WidgetRegistry::class)->widgetNames();

        foreach ($names as $name) {
            $this->getJson("/lamentor/api/schema/controls/{$name}")
                ->assertOk()
                ->assertJsonStructure(['controls', 'defaults']);
        }
        foreach (['container', 'section', 'column'] as $type) {
            $this->getJson("/lamentor/api/schema/controls/{$type}")->assertOk();
        }
    }

    public function test_large_documents_render(): void
    {
        $widgets = [];
        $types = ['heading' => ['title' => 'T'], 'text-editor' => ['editor' => '<p>x</p>'], 'button' => ['text' => 'B'], 'icon-box' => [], 'spacer' => []];
        for ($i = 0; $i < 300; $i++) {
            $type = array_keys($types)[$i % count($types)];
            $widgets[] = ['id' => 'w' . $i, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $types[$type], 'elements' => []];
        }
        $tree = [['id' => 'root', 'elType' => 'container', 'settings' => [], 'elements' => $widgets, 'isInner' => false]];

        $start = microtime(true);
        $doc = $this->app->make(Renderer::class)->document($tree, 1);
        $elapsed = microtime(true) - $start;

        $this->assertSame(300, substr_count($doc->html, 'data-element_type="widget"'));
        $this->assertLessThan(20, $elapsed, 'rendering 300 widgets should be far below this');
        fwrite(STDERR, sprintf("\n[300 widgets rendered in %.2fs]\n", $elapsed));
    }
}
