<?php

namespace Lamentor\Tests;

use Lamentor\Kit\Kit;
use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Orchestra\Testbench\TestCase;

class KitTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function heading(array $settings): string
    {
        return json_encode([['id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings, 'elements' => []]]);
    }

    public function test_kit_resolves_system_and_custom_globals(): void
    {
        $kit = new Kit(['custom_colors' => [['_id' => 'brand', 'title' => 'Brand', 'color' => '#ff0066']]]);

        $this->assertSame('#6EC1E4', $kit->resolve('globals/colors?id=primary')['value']);
        $this->assertSame('#ff0066', $kit->resolve('globals/colors?id=brand')['value']);
        $this->assertSame('Roboto Slab', $kit->resolve('globals/typography?id=secondary')['value']['typography_font_family']);
        $this->assertNull($kit->resolve('globals/colors?id=missing'));
        $this->assertNull($kit->resolve('nonsense'));
    }

    public function test_kit_css_publishes_variables(): void
    {
        $css = $this->app->make(Renderer::class)->document($this->heading([]), 7)->css;

        $this->assertStringContainsString('.elementor-kit-1{--e-global-color-primary:#6EC1E4;', $css);
        $this->assertStringContainsString('--e-global-typography-primary-font-family:"Roboto";', $css);
        $this->assertStringContainsString('--e-global-typography-primary-font-weight:600;', $css);
    }

    public function test_color_global_becomes_a_variable_reference(): void
    {
        $css = $this->app->make(Renderer::class)->cssForJson(
            $this->heading(['__globals__' => ['title_color' => 'globals/colors?id=accent']]),
            3
        );

        $this->assertStringContainsString('.elementor-heading-title{color:var( --e-global-color-accent );}', $css);
    }

    public function test_typography_global_expands_to_every_defined_property(): void
    {
        $css = $this->app->make(Renderer::class)->cssForJson(
            $this->heading(['__globals__' => ['typography_typography' => 'globals/typography?id=primary']]),
            3
        );

        $this->assertStringContainsString('font-family:var( --e-global-typography-primary-font-family ), Sans-serif;', $css);
        $this->assertStringContainsString('font-weight:var( --e-global-typography-primary-font-weight );', $css);
        $this->assertStringNotContainsString('font-size', $css, 'the global defines no size');
    }

    public function test_unknown_global_emits_nothing(): void
    {
        $css = $this->app->make(Renderer::class)->cssForJson(
            $this->heading(['__globals__' => ['title_color' => 'globals/colors?id=nope']]),
            3
        );

        $this->assertStringNotContainsString('color:', $css);
    }

    public function test_document_reports_fonts_and_stylesheets(): void
    {
        $doc = $this->app->make(Renderer::class)->document(
            $this->heading(['typography_typography' => 'custom', 'typography_font_family' => 'Lobster']),
            9
        );

        $this->assertContains('Lobster', $doc->fonts);
        $this->assertContains('Roboto', $doc->fonts);
        $this->assertNotContains('Arial', $doc->fonts);
        $this->assertStringContainsString('family=Lobster%3A100', (string) $doc->fontsUrl);
        $this->assertStringContainsString('display=auto', (string) $doc->fontsUrl);
        $this->assertSame(['css/frontend.min.css', 'lib/eicons/css/elementor-icons.min.css', 'css/widget-heading.min.css'], $doc->stylesheets);
        $this->assertStringContainsString('data-elementor-id="9" class="elementor elementor-9"', $doc->html);
        $this->assertStringContainsString('.elementor-9 .elementor-element.elementor-element-h1 .elementor-heading-title{font-family:"Lobster", Sans-serif;}', $doc->css);
    }

    public function test_system_fonts_are_not_requested_from_google(): void
    {
        $doc = $this->app->make(Renderer::class)->document(
            $this->heading(['typography_typography' => 'custom', 'typography_font_family' => 'Arial']),
            9
        );

        $this->assertNotContains('Arial', $doc->fonts);
    }
}
