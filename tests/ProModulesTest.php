<?php

namespace Lamentor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Lamentor\LamentorServiceProvider;
use Lamentor\Models\Document;
use Lamentor\Models\FormSubmission;
use Lamentor\Rendering\Renderer;
use Lamentor\Templates\TemplateMatcher;
use Orchestra\Testbench\TestCase;

class ProModulesTest extends TestCase
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
        $app['config']->set('lamentor.forms_middleware', ['web']);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
    }

    private function formDoc(array $settings = [], string $status = 'publish'): Document
    {
        return Document::create(['title' => 'Contact', 'status' => $status, 'data' => [[
            'id' => 'c1', 'elType' => 'container', 'settings' => [], 'isInner' => false, 'elements' => [
                ['id' => 'f1', 'elType' => 'widget', 'widgetType' => 'form', 'elements' => [], 'settings' => $settings + [
                    'email_to' => 'owner@example.com', 'success_message' => 'Sent!',
                    'form_fields' => [
                        ['_id' => 'name', 'field_type' => 'text', 'field_label' => 'Name', 'required' => 'yes'],
                        ['_id' => 'email', 'field_type' => 'email', 'field_label' => 'Email', 'required' => 'yes'],
                        ['_id' => 'plan', 'field_type' => 'select', 'field_label' => 'Plan', 'field_options' => "Free\nPro"],
                    ],
                ]],
            ],
        ]]]);
    }

    public function test_form_renders_fields_honeypot_and_action(): void
    {
        $doc = $this->formDoc();
        $html = $doc->render()->html;

        $this->assertStringContainsString('name="fields[name]"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('<option value="Pro"', $html);
        $this->assertStringContainsString('name="lm_hp"', $html);
        $this->assertStringContainsString("/lamentor/forms/{$doc->id}/f1", $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_submission_is_validated_stored_and_mailed(): void
    {
        Mail::fake();
        $doc = $this->formDoc();
        $url = "/lamentor/forms/{$doc->id}/f1";

        $this->postJson($url, ['fields' => ['name' => '', 'email' => 'nope']])
            ->assertStatus(422)->assertJsonPath('errors.name', 'The Name field is required.');
        $this->assertSame(0, FormSubmission::count());

        $this->postJson($url, ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'plan' => 'Hacker']])->assertStatus(422);

        $this->postJson($url, ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'plan' => 'Pro', 'evil' => 'x']])
            ->assertOk()->assertJsonPath('message', 'Sent!');

        $row = FormSubmission::first();
        $this->assertSame('Ada', $row->data['name']['value']);
        $this->assertArrayNotHasKey('evil', $row->data, 'undeclared fields are dropped');
        $this->assertNull($row->ip, 'IP is not stored by default');
        Mail::assertNothingQueued();
    }

    public function test_honeypot_is_silent_and_unpublished_or_wrong_targets_404(): void
    {
        $doc = $this->formDoc();
        $this->postJson("/lamentor/forms/{$doc->id}/f1", ['lm_hp' => 'bot', 'fields' => ['name' => 'x', 'email' => 'x@x.com']])->assertOk();
        $this->assertSame(0, FormSubmission::count());

        $this->postJson("/lamentor/forms/{$doc->id}/c1", [])->assertNotFound();
        $this->postJson("/lamentor/forms/{$doc->id}/missing", [])->assertNotFound();
        $draft = $this->formDoc([], 'draft');
        $this->postJson("/lamentor/forms/{$draft->id}/f1", ['fields' => ['name' => 'x', 'email' => 'x@x.com']])->assertNotFound();
    }

    public function test_form_defaults_come_from_stored_document_not_the_request(): void
    {
        $doc = $this->formDoc(['email_to' => 'not-an-email']);
        $this->postJson("/lamentor/forms/{$doc->id}/f1", ['fields' => ['name' => 'Ada', 'email' => 'a@b.co'], 'email_to' => 'attacker@evil.test'])->assertOk();

        $this->assertSame(1, FormSubmission::count());
    }

    public function test_form_redirect_only_allows_http_or_relative(): void
    {
        $ok = $this->formDoc(['redirect_to' => ['url' => '/thanks']]);
        $this->postJson("/lamentor/forms/{$ok->id}/f1", ['fields' => ['name' => 'a', 'email' => 'a@b.co']])->assertJsonPath('redirect', '/thanks');

        $bad = $this->formDoc(['redirect_to' => ['url' => 'javascript:alert(1)']]);
        $this->postJson("/lamentor/forms/{$bad->id}/f1", ['fields' => ['name' => 'a', 'email' => 'a@b.co']])->assertJsonPath('redirect', null);
    }

    public function test_form_style_controls_generate_css(): void
    {
        $css = app(Renderer::class)->cssForJson(json_encode([['id' => 'f1', 'elType' => 'widget', 'widgetType' => 'form', 'elements' => [],
            'settings' => ['button_bg' => '#123456', 'field_gap' => ['size' => 12, 'unit' => 'px']]]]), 4);

        $this->assertStringContainsString('.elementor-button{background-color:#123456;}', $css);
        $this->assertStringContainsString('.elementor-field-group{margin-bottom:12px;}', $css);
    }

    public function test_templates_pick_the_most_specific_match(): void
    {
        $make = fn (string $title, array $conditions, string $status = 'publish') => Document::create([
            'title' => $title, 'type' => 'header', 'status' => $status, 'data' => [], 'settings' => ['conditions' => $conditions],
        ]);
        $make('Everywhere', [['type' => 'all']]);
        $make('Blog header', [['type' => 'path', 'value' => 'blog/*']]);
        $make('Blog but not drafts', [['type' => 'all'], ['type' => 'path', 'value' => 'blog/drafts*', 'exclude' => true]]);
        $make('Draft header', [['type' => 'path', 'value' => 'blog/*']], 'draft');
        $matcher = new TemplateMatcher();

        $this->assertSame('Blog header', $matcher->forLocation('header', Request::create('/blog/post-1'))->title);
        $about = $matcher->forLocation('header', Request::create('/about'))->title;
        $this->assertContains($about, ['Everywhere', 'Blog but not drafts']);
        $this->assertNotSame('Blog but not drafts', $matcher->forLocation('header', Request::create('/blog/drafts-x'))->title);
        $this->assertNull($matcher->forLocation('footer', Request::create('/')));
        $this->assertNull($matcher->forLocation('not-a-location', Request::create('/')));
    }

    public function test_template_component_renders_matching_document_or_nothing(): void
    {
        Document::create(['title' => 'H', 'type' => 'header', 'status' => 'publish', 'settings' => ['conditions' => [['type' => 'all']]], 'data' => [[
            'id' => 'a', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Site header'], 'elements' => [],
        ]]]);

        $this->assertStringContainsString('Site header', Blade::render('<x-lamentor::template location="header" />'));
        $this->assertSame('', trim(Blade::render('<x-lamentor::template location="footer" />')));
    }
}
