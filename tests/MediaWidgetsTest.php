<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Orchestra\Testbench\TestCase;

class MediaWidgetsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function doc(string $type, array $settings): string
    {
        return json_encode([['id' => 'w1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => []]]);
    }

    private function html(string $type, array $settings = []): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->doc($type, $settings));
    }

    private function css(string $type, array $settings): string
    {
        return $this->app->make(Renderer::class)->cssForJson($this->doc($type, $settings), 9);
    }

    public function test_image_renders_img_with_size_classes(): void
    {
        $out = $this->html('image', ['image' => ['url' => 'https://x.test/a.jpg', 'id' => 12, 'alt' => 'A']]);

        $this->assertStringContainsString('src="https://x.test/a.jpg"', $out);
        $this->assertStringContainsString('alt="A"', $out);
        $this->assertStringContainsString('attachment-large size-large wp-image-12', $out);
    }

    public function test_image_without_url_renders_nothing_inside_wrapper(): void
    {
        $this->assertStringNotContainsString('<img', $this->html('image', ['image' => ['url' => '']]));
    }

    public function test_image_with_custom_caption_and_link(): void
    {
        $out = $this->html('image', [
            'image' => ['url' => '/a.jpg'], 'caption_source' => 'custom', 'caption' => 'Cap <script>x</script>',
            'link_to' => 'custom', 'link' => ['url' => 'https://x.test'],
        ]);

        $this->assertStringContainsString('<figure class="wp-caption"><a href="https://x.test"', $out);
        $this->assertStringContainsString('wp-caption-text">Cap </figcaption>', $out);
    }

    public function test_image_rejects_javascript_src(): void
    {
        $this->assertStringNotContainsString('<img', $this->html('image', ['image' => ['url' => 'javascript:alert(1)']]));
    }

    public function test_image_css(): void
    {
        $css = $this->css('image', ['width' => ['size' => 50, 'unit' => '%'], 'width_mobile' => ['size' => 100, 'unit' => '%']]);

        $this->assertStringContainsString('img{width:50%;}', $css);
        $this->assertStringContainsString('@media(max-width:767px){', $css);
    }

    public function test_icon_renders_font_icon_with_view_and_shape_classes(): void
    {
        $out = $this->html('icon', ['view' => 'stacked', 'shape' => 'square']);

        $this->assertStringContainsString('elementor-view-stacked', $out);
        $this->assertStringContainsString('elementor-shape-square', $out);
        $this->assertStringContainsString('<i aria-hidden="true" class="fas fa-star"></i>', $out);
        $this->assertStringContainsString('<div class="elementor-icon">', $out);
    }

    public function test_icon_class_value_cannot_break_out_of_attribute(): void
    {
        $out = $this->html('icon', ['selected_icon' => ['value' => 'fa" onmouseover="x', 'library' => 'fa-solid']]);

        $this->assertStringNotContainsString('onmouseover="', $out);
    }

    public function test_icon_with_link_becomes_anchor(): void
    {
        $out = $this->html('icon', ['link' => ['url' => 'https://x.test']]);

        $this->assertMatchesRegularExpression('#<a class="elementor-icon" href="https://x.test">#', $out);
    }

    public function test_icon_css_depends_on_view(): void
    {
        $css = $this->css('icon', ['primary_color' => '#f00']);

        $this->assertStringContainsString('.elementor-view-stacked .elementor-icon{background-color:#f00;}', $css);
        $this->assertStringContainsString('.elementor-view-default .elementor-icon{color:#f00;border-color:#f00;}', $css);
    }

    public function test_icon_border_width_only_when_framed(): void
    {
        $bw = ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2, 'unit' => 'px'];

        $this->assertStringNotContainsString('border-width', $this->css('icon', ['border_width' => $bw]));
        $this->assertStringContainsString('border-width:2px 2px 2px 2px', $this->css('icon', ['view' => 'framed', 'border_width' => $bw]));
    }

    public function test_icon_box_structure_and_link(): void
    {
        $out = $this->html('icon-box', ['title_text' => 'Hi', 'description_text' => 'Desc', 'title_size' => 'h4', 'link' => ['url' => 'https://x.test']]);

        $this->assertStringContainsString('elementor-icon-box-wrapper', $out);
        $this->assertStringContainsString('elementor-position-block-start', $out);
        $this->assertMatchesRegularExpression('#<h4 class="elementor-icon-box-title"><a href="https://x.test">Hi</a></h4>#', $out);
        $this->assertStringContainsString('<p class="elementor-icon-box-description">Desc</p>', $out);
    }

    public function test_icon_box_rejects_unsafe_title_tag(): void
    {
        $out = $this->html('icon-box', ['title_size' => 'script']);

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<h3 class="elementor-icon-box-title">', $out);
    }

    public function test_icon_box_position_class_is_responsive(): void
    {
        $this->assertStringContainsString('elementor-mobile-position-inline-start', $this->html('icon-box', ['position_mobile' => 'inline-start']));
    }
}
