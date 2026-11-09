<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Widgets\WidgetRegistry;
use Orchestra\Testbench\TestCase;

class AllWidgetsTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function doc(string $type, array $settings = [], array $children = [], string $elType = 'widget', bool $inner = false): string
    {
        return json_encode([[
            'id' => 'a1b2c3d4', 'elType' => $elType, 'widgetType' => $elType === 'widget' ? $type : null,
            'settings' => $settings, 'elements' => $children, 'isInner' => $inner,
        ]]);
    }

    private function html(string $type, array $settings = []): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->doc($type, $settings));
    }

    private function css(string $type, array $settings = []): string
    {
        return $this->app->make(Renderer::class)->cssForJson($this->doc($type, $settings), 1);
    }

    public function test_every_registered_widget_renders_and_styles_with_defaults(): void
    {
        $names = $this->app->make(WidgetRegistry::class)->widgetNames();
        $this->assertGreaterThan(25, count($names));

        foreach ($names as $name) {
            $out = $this->html($name);
            $this->assertStringContainsString("elementor-widget-{$name}", $out, $name);
            $this->assertIsString($this->css($name), $name);
        }
    }

    public function test_legacy_section_and_column_markup(): void
    {
        $column = ['id' => 'c', 'elType' => 'column', 'settings' => ['_column_size' => 50], 'elements' => [
            ['id' => 'w', 'elType' => 'widget', 'widgetType' => 'spacer', 'settings' => [], 'elements' => []],
        ], 'isInner' => false];
        $json = $this->doc('', ['layout' => 'full_width'], [$column], 'section');

        $out = $this->app->make(Renderer::class)->renderJson($json);

        $this->assertStringStartsWith('<section ', $out);
        $this->assertStringContainsString('elementor-section elementor-top-section', $out);
        $this->assertStringContainsString('elementor-section-full_width', $out);
        $this->assertStringContainsString('<div class="elementor-container elementor-column-gap-default">', $out);
        $this->assertStringContainsString('elementor-column elementor-col-50 elementor-top-column', $out);
        $this->assertStringContainsString('elementor-widget-wrap elementor-element-populated', $out);
    }

    public function test_common_advanced_controls_apply_to_every_widget(): void
    {
        $out = $this->html('heading', ['_css_classes' => 'my-class', '_element_id' => 'hero']);
        $this->assertStringContainsString('my-class', $out);
        $this->assertStringContainsString('id="hero"', $out);

        $css = $this->css('heading', ['_margin' => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4, 'unit' => 'px', 'isLinked' => false]]);
        $this->assertStringContainsString('margin:1px 2px 3px 4px', $css);
    }

    public function test_hidden_on_device_adds_visibility_classes(): void
    {
        $out = $this->html('heading', ['hide_mobile' => 'hidden-mobile']);
        $this->assertStringContainsString('elementor-hidden-mobile', $out);
    }

    public function test_entrance_animation_marks_element_invisible(): void
    {
        $this->assertStringContainsString('elementor-invisible', $this->html('heading', ['_animation' => 'fadeIn']));
        $this->assertStringNotContainsString('elementor-invisible', $this->html('heading', ['_animation' => 'none']));
    }

    public function test_tabs_markup_and_ids(): void
    {
        $out = $this->html('tabs', ['tabs' => [
            ['tab_title' => 'One', 'tab_content' => '<p>First</p>', '_id' => 'a'],
            ['tab_title' => 'Two', 'tab_content' => '<p>Second<script>x</script></p>', '_id' => 'b'],
        ]]);

        $this->assertStringContainsString('role="tablist"', $out);
        $this->assertStringContainsString('aria-selected="true"', $out);
        $this->assertStringContainsString('data-tab="2"', $out);
        $this->assertStringContainsString('<p>First</p>', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertMatchesRegularExpression('/id="elementor-tab-content-\d+2"[^>]*hidden/', $out);
    }

    public function test_accordion_and_toggle_use_their_own_class_names(): void
    {
        $tabs = ['tabs' => [['tab_title' => 'Q', 'tab_content' => 'A', '_id' => 'a']]];

        $accordion = $this->html('accordion', $tabs);
        $this->assertStringContainsString('elementor-accordion-item', $accordion);
        $this->assertStringContainsString('elementor-accordion-title', $accordion);

        $toggle = $this->html('toggle', $tabs);
        $this->assertStringContainsString('elementor-toggle-item', $toggle);
        $this->assertStringContainsString('elementor-toggle-title', $toggle);
    }

    public function test_faq_schema_json_cannot_break_out_of_script_tag(): void
    {
        $out = $this->html('accordion', ['faq_schema' => 'yes', 'tabs' => [['tab_title' => '</script><b>', 'tab_content' => 'a', '_id' => 'a']]]);

        $this->assertStringContainsString('application/ld+json', $out);
        $this->assertSame(1, substr_count($out, '</script>'));
    }

    public function test_progress_clamps_and_sets_aria(): void
    {
        $out = $this->html('progress', ['title' => 'Skill', 'percent' => ['size' => 250, 'unit' => '%'], 'display_percentage' => 'show']);

        $this->assertStringContainsString('aria-valuenow="100"', $out);
        $this->assertStringContainsString('data-max="100"', $out);
        $this->assertStringContainsString('role="progressbar"', $out);
        $this->assertStringContainsString('elementor-progress-percentage">100%', $out);
    }

    public function test_counter_exposes_data_attributes(): void
    {
        $out = $this->html('counter', ['starting_number' => 0, 'ending_number' => 250, 'duration' => 1500, 'thousand_separator' => 'default']);

        $this->assertStringContainsString('data-to-value="250"', $out);
        $this->assertStringContainsString('data-duration="1500"', $out);
        $this->assertStringContainsString('data-delimiter=","', $out);
    }

    public function test_star_rating_fills_partial_stars(): void
    {
        $out = $this->html('star-rating', ['rating' => 3.5, 'rating_scale' => '5']);

        $this->assertSame(3, substr_count($out, 'elementor-star-full'));
        $this->assertSame(1, substr_count($out, 'elementor-star-5'));
        $this->assertSame(1, substr_count($out, 'elementor-star-empty'));
        $this->assertStringContainsString('Rated 3.5 out of 5', $out);
    }

    public function test_rating_marks_icon_width(): void
    {
        $out = $this->html('rating', ['rating_value' => '2.5', 'rating_scale' => ['size' => 5, 'unit' => 'px']]);

        $this->assertSame(5, substr_count($out, 'e-icon-unmarked'));
        $this->assertStringContainsString('--e-rating-icon-marked-width: 50%;', $out);
    }

    public function test_icon_list_links_and_inline_view(): void
    {
        $out = $this->html('icon-list', ['view' => 'inline', 'icon_list' => [
            ['text' => 'One', '_id' => 'a', 'selected_icon' => ['value' => 'fas fa-check', 'library' => 'fa-solid'], 'link' => ['url' => 'https://x.test']],
        ]]);

        $this->assertStringContainsString('elementor-inline-items', $out);
        $this->assertStringContainsString('<a href="https://x.test"><span class="elementor-icon-list-icon">', $out);
        $this->assertStringContainsString('elementor-icon-list-text">One', $out);
    }

    public function test_social_icons_derive_the_network_name(): void
    {
        $out = $this->html('social-icons', ['social_icon_list' => [
            ['social_icon' => ['value' => 'fab fa-facebook', 'library' => 'fa-brands'], '_id' => 'x1', 'link' => ['url' => 'https://f.test']],
            ['social_icon' => ['value' => 'fab fa-x-twitter', 'library' => 'fa-brands'], '_id' => 'x2', 'link' => ['url' => 'https://t.test']],
        ]]);

        $this->assertStringContainsString('elementor-social-icon-facebook', $out);
        $this->assertStringContainsString('elementor-social-icon-x-twitter', $out);
        $this->assertStringContainsString('role="list"', $out);
    }

    public function test_divider_pattern_uses_inline_svg_variable(): void
    {
        $out = $this->html('divider', ['separator_type' => 'pattern', 'style' => 'curly']);

        $this->assertStringContainsString('--divider-pattern-url: url(&quot;data:image/svg+xml,%3Csvg', $out);
    }

    public function test_video_embeds(): void
    {
        $vimeo = $this->html('video', ['video_type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/123456789']);
        $this->assertStringContainsString('src="https://player.vimeo.com/video/123456789?', $vimeo);

        $youtube = $this->html('video', ['video_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=abc123DEF45']);
        $this->assertStringContainsString('class="elementor-video"', $youtube);
        $this->assertStringContainsString('data-settings=', $youtube);

        $hosted = $this->html('video', ['video_type' => 'hosted', 'hosted_url' => ['url' => '/v.mp4'], 'controls' => 'yes']);
        $this->assertStringContainsString('<video class="elementor-video" src="/v.mp4"', $hosted);
    }

    public function test_audio_only_accepts_soundcloud(): void
    {
        $this->assertStringContainsString('w.soundcloud.com/player', $this->html('audio', ['link' => ['url' => 'https://soundcloud.com/a/b']]));
        $this->assertStringNotContainsString('<iframe', $this->html('audio', ['link' => ['url' => 'https://evil.test/x']]));
    }

    public function test_carousel_and_gallery_render_images(): void
    {
        $images = [['id' => 1, 'url' => '/a.jpg'], ['id' => 2, 'url' => '/b.jpg']];

        $carousel = $this->html('image-carousel', ['carousel' => $images]);
        $this->assertSame(2, substr_count($carousel, 'swiper-slide-inner'));
        $this->assertStringContainsString('elementor-swiper-button-next', $carousel);

        $gallery = $this->html('image-gallery', ['wp_gallery' => $images, 'gallery_columns' => '2']);
        $this->assertStringContainsString('gallery-columns-2', $gallery);
        $this->assertSame(2, substr_count($gallery, 'gallery-item'));
    }

    public function test_google_map_iframe_url_is_encoded(): void
    {
        $out = $this->html('google_maps', ['address' => 'Dublin & "Co"']);

        $this->assertStringContainsString('q=Dublin%20%26%20%22Co%22', $out);
    }

    public function test_menu_anchor_sanitizes_id(): void
    {
        $this->assertStringContainsString('id="my-anchor"', $this->html('menu-anchor', ['anchor' => 'my-anchor']));

        $evil = $this->html('menu-anchor', ['anchor' => 'a"><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script', $evil);
        $this->assertStringContainsString('id="ascriptalert1script"', $evil);
    }
}
