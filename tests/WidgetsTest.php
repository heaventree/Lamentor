<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Html;
use Orchestra\Testbench\TestCase;

class WidgetsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function widget(string $type, array $settings): string
    {
        return json_encode([[
            'id' => 'w1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [],
        ]]);
    }

    private function html(string $type, array $settings): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->widget($type, $settings));
    }

    private function css(string $type, array $settings): string
    {
        return $this->app->make(Renderer::class)->cssForJson($this->widget($type, $settings), 7);
    }

    public function test_sanitizer_strips_scripts_handlers_and_js_urls(): void
    {
        $dirty = '<p onclick="x()">Hi <script>alert(1)</script><a href=" java&#x09;script:alert(1)" onmouseover="y()">link</a><img src=x onerror=z()></p>';
        $clean = Html::sanitize($dirty, Html::RICH);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript', strtolower($clean));
        $this->assertStringContainsString('link', $clean);
    }

    public function test_button_renders_link_and_alignment_classes(): void
    {
        $out = $this->html('button', [
            'text' => 'Go', 'link' => ['url' => 'https://example.com', 'is_external' => 'on'],
            'align' => 'center', 'align_mobile' => 'left', 'button_type' => 'info',
        ]);

        $this->assertStringContainsString('elementor-align-center', $out);
        $this->assertStringContainsString('elementor-mobile-align-left', $out);
        $this->assertStringContainsString('elementor-button-info', $out);
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('rel="noopener"', $out);
        $this->assertStringContainsString('<span class="elementor-button-text">Go</span>', $out);
    }

    public function test_button_without_link_gets_role_button(): void
    {
        $this->assertStringContainsString('role="button"', $this->html('button', ['text' => 'x', 'link' => ['url' => '']]));
    }

    public function test_button_rejects_javascript_url(): void
    {
        $out = $this->html('button', ['text' => 'x', 'link' => ['url' => 'javascript:alert(1)']]);

        $this->assertStringNotContainsString('javascript', $out);
    }

    public function test_button_css_border_and_shadow(): void
    {
        $css = $this->css('button', [
            'border_border' => 'solid',
            'border_width' => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4, 'unit' => 'px'],
            'border_color' => '#000',
            'button_box_shadow_box_shadow_type' => 'yes',
            'button_box_shadow_box_shadow' => ['horizontal' => 0, 'vertical' => 2, 'blur' => 5, 'spread' => 0, 'color' => 'rgba(0,0,0,.5)'],
        ]);

        $this->assertStringContainsString('border-style:solid;', $css);
        $this->assertStringContainsString('border-width:1px 2px 3px 4px;', $css);
        $this->assertStringContainsString('border-color:#000;', $css);
        $this->assertStringContainsString('box-shadow:0px 2px 5px 0px rgba(0,0,0,.5);', $css);
    }

    public function test_border_width_is_skipped_when_border_type_none(): void
    {
        $css = $this->css('button', [
            'border_border' => 'none',
            'border_width' => ['top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'unit' => 'px'],
        ]);

        $this->assertStringNotContainsString('border-width', $css);
    }

    public function test_spacer_renders_only_with_a_size_and_sets_custom_property(): void
    {
        $this->assertStringContainsString('elementor-spacer-inner', $this->html('spacer', []));
        $this->assertStringNotContainsString('elementor-spacer-inner', $this->html('spacer', ['space' => ['size' => 0, 'unit' => 'px']]));
        $this->assertStringContainsString('--spacer-size:80px;', $this->css('spacer', ['space' => ['size' => 80, 'unit' => 'px']]));
    }

    public function test_text_editor_sanitizes_but_keeps_block_markup(): void
    {
        $out = $this->html('text-editor', ['editor' => '<h3>Title</h3><p>Body <b>bold</b><script>x</script></p>']);

        $this->assertStringContainsString('<h3>Title</h3>', $out);
        $this->assertStringContainsString('<b>bold</b>', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function test_html_widget_is_verbatim(): void
    {
        $this->assertStringContainsString('<script>1</script>', $this->html('html', ['html' => '<script>1</script>']));
    }
}
