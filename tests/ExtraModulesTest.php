<?php

namespace Lamentor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Lamentor\Dynamic\DynamicTags;
use Lamentor\Dynamic\LoopSources;
use Lamentor\LamentorServiceProvider;
use Lamentor\Models\Document;
use Lamentor\Rendering\Renderer;
use Orchestra\Testbench\TestCase;

class ExtraModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.name', 'Acme Site');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
    }

    private function w(string $type, array $settings = []): array
    {
        return ['id' => 'w' . substr(md5($type . json_encode($settings)), 0, 6), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => []];
    }

    private function html(array $tree): string
    {
        return app(Renderer::class)->document($tree, 1)->html;
    }

    public function test_dynamic_tags_fill_text_and_link_settings(): void
    {
        $html = $this->html([$this->w('heading', [
            'title' => 'static',
            'link' => ['url' => ''],
            '__dynamic__' => ['title' => ['tag' => 'site-title'], 'link' => ['tag' => 'request-url']],
        ])]);

        $this->assertStringContainsString('Acme Site', $html);
        $this->assertStringNotContainsString('static', $html);
        $this->assertStringContainsString('<a href="http', $html);
    }

    public function test_field_tag_reads_context_and_escapes_output(): void
    {
        $tags = app(DynamicTags::class);
        $tree = [$this->w('heading', ['__dynamic__' => ['title' => ['tag' => 'field', 'settings' => ['key' => 'name']]]])];

        $html = $tags->with(['name' => 'Ada <script>x</script>'], fn () => $this->html($tree));

        $this->assertStringContainsString('Ada', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame('', $tags->context() === [] ? '' : 'leaked', 'context is popped after use');
        $this->assertNull($tags->resolve('nope'));
    }

    public function test_dynamic_binding_ignores_bad_keys_and_unknown_tags(): void
    {
        $out = app(DynamicTags::class)->apply(['a' => 'keep'], ['a' => ['tag' => 'missing'], 'b c' => ['tag' => 'site-title'], 'x' => 'junk']);

        $this->assertSame(['a' => 'keep'], $out);
    }

    public function test_loop_grid_repeats_template_per_item_with_context(): void
    {
        $template = Document::create(['title' => 'Card', 'type' => 'loop-item', 'status' => 'publish', 'data' => [
            ['id' => 'c1', 'elType' => 'container', 'settings' => [], 'isInner' => false, 'elements' => [
                $this->w('heading', ['__dynamic__' => ['title' => ['tag' => 'field', 'settings' => ['key' => 'title']]]]),
            ]],
        ]]);
        app(LoopSources::class)->register('people', fn (int $limit) => array_slice([['title' => 'Ada'], ['title' => 'Grace'], ['title' => 'Linus']], 0, $limit));

        $html = $this->html([$this->w('loop-grid', ['items_source' => 'people', 'template_id' => $template->id, 'limit' => 2])]);

        $this->assertSame(2, substr_count($html, 'lamentor-loop-item'));
        $this->assertStringContainsString('Ada', $html);
        $this->assertStringContainsString('Grace', $html);
        $this->assertStringNotContainsString('Linus', $html);
    }

    public function test_loop_grid_handles_missing_template_and_unknown_source(): void
    {
        $html = $this->html([$this->w('loop-grid', ['items_source' => 'nope', 'template_id' => 999, 'empty_text' => 'Nothing here'])]);

        $this->assertStringContainsString('Nothing here', $html);
        $this->assertStringNotContainsString('lamentor-loop-item', $html);
    }

    public function test_nav_menu_renders_links_dropdowns_and_drops_unsafe_urls(): void
    {
        $html = $this->html([$this->w('nav-menu', ['menu_items' => [
            ['_id' => 'a', 'label' => 'Home', 'link' => ['url' => '/']],
            ['_id' => 'b', 'label' => 'More', 'link' => ['url' => ''], 'submenu' => "Docs|/docs\nEvil|javascript:alert(1)\nSite|https://example.com"],
        ]])]);

        $this->assertStringContainsString('<a href="/">Home</a>', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString('href="/docs"', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_nav_menu_toggle_stays_hidden_on_wide_screens(): void
    {
        $html = $this->html([$this->w('nav-menu', ['menu_items' => [['_id' => 'a', 'label' => 'Home', 'link' => ['url' => '/']]]])]);

        // `.lamentor-nav button{all:unset;display:inline-block}` must not win over the rule that hides the toggle
        $this->assertStringContainsString('.lamentor-nav .lamentor-nav-toggle{display:none}', $html);
        $this->assertStringNotContainsString('}.lamentor-nav-toggle{display:none}', $html);
    }

    public function test_nav_menu_mega_panel_renders_another_document(): void
    {
        $mega = Document::create(['title' => 'Mega', 'type' => 'section', 'status' => 'publish', 'data' => [$this->w('heading', ['title' => 'Mega content'])]]);

        $html = $this->html([$this->w('nav-menu', ['menu_items' => [['_id' => 'a', 'label' => 'Shop', 'mega_template' => $mega->id]]])]);

        $this->assertStringContainsString('Mega content', $html);
        $this->assertStringContainsString('lamentor-nav-panel', $html);
    }

    public function test_sticky_and_scroll_reveal_controls_apply_to_every_element(): void
    {
        $css = app(Renderer::class)->cssForJson(json_encode([
            $this->w('heading', ['lm_sticky' => 'top', 'lm_sticky_offset_top' => ['size' => 12, 'unit' => 'px'], 'lm_reveal' => 'up']),
        ]), 5);

        // The offset replaces the default `top:0` inside the same rule.
        $this->assertStringContainsString('position:sticky;top:12px;z-index:99;', $css);
        $this->assertStringContainsString('animation-timeline:view();', $css);

        $container = app(Renderer::class)->cssForJson(json_encode([['id' => 'c', 'elType' => 'container', 'settings' => ['lm_sticky' => 'bottom'], 'elements' => []]]), 5);
        $this->assertStringContainsString('position:sticky;bottom:0;', $container);
    }

    public function test_reveal_pulls_in_keyframes_stylesheet_only_when_used(): void
    {
        $with = app(Renderer::class)->document([$this->w('heading', ['lm_reveal' => 'fade'])], 1)->stylesheets;
        $without = app(Renderer::class)->document([$this->w('heading')], 1)->stylesheets;

        $this->assertContains('css/lamentor.css', $with);
        $this->assertNotContains('css/lamentor.css', $without);
    }

    public function test_popups_component_prints_matching_popups_with_triggers(): void
    {
        Document::create(['title' => 'Promo', 'type' => 'popup', 'status' => 'publish', 'data' => [$this->w('heading', ['title' => 'Join us'])],
            'settings' => ['conditions' => [['type' => 'all']], 'trigger' => 'delay', 'delay' => 5, 'once' => true]]);
        Document::create(['title' => 'Draft', 'type' => 'popup', 'status' => 'draft', 'data' => [$this->w('heading', ['title' => 'Hidden draft'])],
            'settings' => ['conditions' => [['type' => 'all']]]]);
        Document::create(['title' => 'Elsewhere', 'type' => 'popup', 'status' => 'publish', 'data' => [$this->w('heading', ['title' => 'Other page'])],
            'settings' => ['conditions' => [['type' => 'path', 'value' => 'nowhere/*']]]]);

        $html = Blade::render('<x-lamentor::popups />');

        $this->assertStringContainsString('Join us', $html);
        $this->assertStringContainsString('data-trigger="delay"', $html);
        $this->assertStringContainsString('data-delay="5"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringNotContainsString('Hidden draft', $html);
        $this->assertStringNotContainsString('Other page', $html);
        $this->assertSame('', trim(Blade::render('<x-lamentor::popups />')) === '' ? '' : '', '');
    }

    public function test_popups_print_nothing_when_none_match(): void
    {
        $this->assertSame('', trim(Blade::render('<x-lamentor::popups />')));
    }
}
