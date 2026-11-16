<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Assets;
use Orchestra\Testbench\TestCase;

class HeroSliderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function doc(array $settings): string
    {
        return json_encode([[
            'id' => 's1', 'elType' => 'widget', 'widgetType' => 'hero-slider', 'settings' => $settings, 'elements' => [],
        ]]);
    }

    private function html(array $settings = []): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->doc($settings));
    }

    public function test_slides_render_with_text_animation_controls_and_settings(): void
    {
        $out = $this->html(['effect' => 'slide', 'autoplay_speed' => 4000, 'slides' => [
            ['title' => 'First', 'text' => 'Hello', 'button_text' => 'Go', 'link' => ['url' => 'https://example.com/x'], 'animation' => 'zoomIn', 'image' => ['url' => 'https://example.com/a.jpg']],
            ['title' => 'Second', 'animation' => 'fadeInLeft'],
        ]]);

        $this->assertStringContainsString('elementor-widget-hero-slider', $out);
        $this->assertStringContainsString('lm-effect-slide', $out);
        $this->assertSame(2, preg_match_all('/class="lm-hero-slide[ "]/', $out));
        $this->assertStringContainsString('lm-a-zoomIn', $out);
        $this->assertStringContainsString('lm-a-fadeInLeft', $out);
        $this->assertStringContainsString('<h1 class="lm-hero-title lm-a-zoomIn"', $out);
        $this->assertStringContainsString('href="https://example.com/x"', $out);
        $this->assertStringContainsString('lm-hero-bg', $out);
        $this->assertStringContainsString('lm-hero-prev', $out);
        $this->assertSame(2, preg_match_all('/class="lm-hero-dot[ "]/', $out));
        $this->assertStringContainsString('autoplay_speed', $out); // data-settings for the script
    }

    public function test_unsafe_input_is_neutralised_and_unknown_animation_falls_back(): void
    {
        $out = $this->html(['slides' => [
            ['title' => 'x<script>alert(1)</script>', 'button_text' => 'b', 'link' => ['url' => 'javascript:alert(1)'], 'animation' => 'evil"onload="x', 'image' => ['url' => 'javascript:alert(1)']],
        ]]);

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('evil', $out);
        $this->assertStringContainsString('lm-a-fadeInUp', $out);
        $this->assertStringNotContainsString('lm-hero-prev', $out, 'a single slide has no arrows');
    }

    public function test_assets_include_its_stylesheet_and_script_only_when_used(): void
    {
        $renderer = $this->app->make(Renderer::class);

        $this->assertContains('css/lamentor-hero-slider.css', $renderer->stylesheetsForJson($this->doc([])));
        $plain = json_encode([['id' => 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [], 'elements' => []]]);
        $this->assertNotContains('css/lamentor-hero-slider.css', $renderer->stylesheetsForJson($plain));

        $scripts = Assets::scriptsFor($renderer->document($this->doc([]), 1));
        $this->assertContains('js/lamentor-hero-slider.js', $scripts['before']);
    }
}
