<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Orchestra\Testbench\TestCase;

class CssTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function css(array $settings): string
    {
        $json = json_encode([[
            'id' => 'h1',
            'elType' => 'widget',
            'widgetType' => 'heading',
            'settings' => $settings,
            'elements' => [],
        ]]);

        return $this->app->make(Renderer::class)->cssForJson($json, 42);
    }

    public function test_color_uses_scoped_wrapper_selector(): void
    {
        $this->assertSame(
            '.elementor-42 .elementor-element.elementor-element-h1 .elementor-heading-title{color:#ff0000;}',
            $this->css(['title_color' => '#ff0000'])
        );
    }

    public function test_responsive_value_goes_into_media_queries_widest_first(): void
    {
        $css = $this->css(['align' => 'left', 'align_mobile' => 'center', 'align_tablet' => 'right']);
        $w = '.elementor-42 .elementor-element.elementor-element-h1';

        $this->assertSame(
            "{$w}{text-align:start;}@media(max-width:1024px){{$w}{text-align:end;}}@media(max-width:767px){{$w}{text-align:center;}}",
            $css
        );
    }

    public function test_typography_only_applies_when_custom(): void
    {
        $slider = ['size' => 32, 'unit' => 'px'];

        $this->assertSame('', $this->css(['typography_font_size' => $slider]));
        $this->assertStringContainsString(
            'font-size:32px;',
            $this->css(['typography_typography' => 'custom', 'typography_font_size' => $slider])
        );
    }

    public function test_rule_with_missing_placeholder_is_skipped(): void
    {
        $css = $this->css(['typography_typography' => 'custom', 'typography_font_size' => ['size' => '', 'unit' => 'px']]);

        $this->assertSame('', $css);
    }

    public function test_zero_is_a_valid_value(): void
    {
        $css = $this->css(['typography_typography' => 'custom', 'typography_letter_spacing' => ['size' => 0, 'unit' => 'px']]);

        $this->assertStringContainsString('letter-spacing:0px;', $css);
    }
}
