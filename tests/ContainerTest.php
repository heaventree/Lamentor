<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Assets;
use Orchestra\Testbench\TestCase;

class ContainerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function container(array $settings, array $children = [], bool $inner = false): string
    {
        return json_encode([[
            'id' => 'c1', 'elType' => 'container', 'settings' => $settings, 'elements' => $children, 'isInner' => $inner,
        ]]);
    }

    private function html(array $settings, array $children = []): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->container($settings, $children));
    }

    private function css(array $settings): string
    {
        return $this->app->make(Renderer::class)->cssForJson($this->container($settings), 5);
    }

    private const W = '.elementor-5 .elementor-element.elementor-element-c1';

    public function test_default_markup_is_boxed_flex_parent_with_inner_wrapper(): void
    {
        $out = $this->html([], [['id' => 'h', 'elType' => 'widget', 'widgetType' => 'spacer', 'settings' => [], 'elements' => []]]);

        $this->assertStringContainsString('e-flex', $out);
        $this->assertStringContainsString('e-con-boxed', $out);
        $this->assertStringContainsString('e-parent', $out);
        $this->assertStringContainsString('<div class="e-con-inner"><div class="elementor-element', $out);
    }

    public function test_full_width_has_no_inner_wrapper_and_nested_is_child(): void
    {
        $out = $this->app->make(Renderer::class)->renderJson($this->container(['content_width' => 'full'], [], true));

        $this->assertStringContainsString('e-con-full', $out);
        $this->assertStringContainsString('e-child', $out);
        $this->assertStringNotContainsString('e-con-inner', $out);
    }

    public function test_grid_container_type_class(): void
    {
        $this->assertStringContainsString('e-grid', $this->html(['container_type' => 'grid']));
    }

    public function test_html_tag_is_allowlisted(): void
    {
        $this->assertStringStartsWith('<section ', $this->html(['html_tag' => 'section']));
        $this->assertStringStartsWith('<div ', $this->html(['html_tag' => 'script']));
        $this->assertStringStartsWith('<div ', $this->html(['html_tag' => 'a']), 'anchor without link falls back');
    }

    public function test_anchor_container_with_link(): void
    {
        $this->assertStringStartsWith('<a ', $this->html(['html_tag' => 'a', 'link' => ['url' => 'https://x.test']]));
    }

    public function test_flex_direction_expands_to_variable_set_and_is_responsive(): void
    {
        $css = $this->css(['flex_direction' => 'row', 'flex_direction_mobile' => 'column']);

        $this->assertStringContainsString('--display:flex;--flex-direction:row;', $css);
        $this->assertStringContainsString('--container-widget-flex-grow:1;', $css);
        $this->assertStringContainsString('@media(max-width:767px){' . self::W . '{--flex-direction:column;', $css);
    }

    public function test_gap_justify_and_padding_set_variables(): void
    {
        $css = $this->css([
            'flex_gap' => ['row' => 10, 'column' => 20, 'unit' => 'px', 'isLinked' => false],
            'flex_justify_content' => 'space-between',
            'padding' => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4, 'unit' => 'em'],
        ]);

        $this->assertStringContainsString('--gap:10px 20px;--row-gap:10px;--column-gap:20px;', $css);
        $this->assertStringContainsString('--justify-content:space-between;', $css);
        $this->assertStringContainsString('--padding-top:1em;--padding-bottom:3em;--padding-left:4em;--padding-right:2em;', $css);
    }

    public function test_flex_controls_are_inactive_for_grid_and_grid_controls_for_flex(): void
    {
        $this->assertStringNotContainsString('--justify-content', $this->css(['container_type' => 'grid', 'flex_justify_content' => 'center']));
        $this->assertStringContainsString('repeat(4, 1fr)', $this->css(['container_type' => 'grid', 'grid_columns_grid' => ['size' => 4, 'unit' => 'fr']]));
        $this->assertStringNotContainsString('repeat(', $this->css(['grid_columns_grid' => ['size' => 4, 'unit' => 'fr']]));
    }

    public function test_align_content_needs_wrap(): void
    {
        $this->assertStringNotContainsString('--align-content', $this->css(['flex_align_content' => 'center']));
        $this->assertStringContainsString('--align-content:center;', $this->css(['flex_wrap' => 'wrap', 'flex_align_content' => 'center']));
    }

    public function test_border_sets_css_variables_used_by_base_stylesheet(): void
    {
        $css = $this->css([
            'border_border' => 'solid',
            'border_width' => ['top' => 1, 'right' => 1, 'bottom' => 1, 'left' => 1, 'unit' => 'px'],
            'border_color' => '#123',
            'background_background' => 'classic',
            'background_color' => '#fff',
        ]);

        $this->assertStringContainsString('--border-style:solid;', $css);
        $this->assertStringContainsString('--border-top-width:1px;', $css);
        $this->assertStringContainsString('--border-color:#123;', $css);
        $this->assertStringContainsString('background-color:#fff;', $css);
    }

    public function test_width_and_boxed_width_variables(): void
    {
        $css = $this->css(['content_width' => 'full', 'width' => ['size' => 50, 'unit' => '%']]);
        $boxed = $this->css(['boxed_width' => ['size' => 900, 'unit' => 'px']]);

        $this->assertStringContainsString('--width:50%;', $css);
        // Boxed width only applies from the first breakpoint above mobile (min_affected_device).
        $this->assertStringContainsString('@media(min-width:768px){' . self::W . '{--content-width:900px;}}', $boxed);
    }

    public function test_required_stylesheets_list_base_and_used_widgets_once(): void
    {
        $widget = fn (string $t) => ['id' => $t . rand(), 'elType' => 'widget', 'widgetType' => $t, 'settings' => [], 'elements' => []];
        $json = $this->container([], [$widget('heading'), $widget('heading'), $widget('icon-box'), $widget('button')]);

        $files = $this->app->make(Renderer::class)->stylesheetsForJson($json);

        $this->assertSame(['css/frontend.min.css', 'lib/eicons/css/elementor-icons.min.css', 'css/widget-heading.min.css', 'css/widget-icon-box.min.css'], $files);
        // Every css/ file listed is bundled with the package.
        foreach ($files as $file) {
            if (str_starts_with($file, 'css/')) {
                $this->assertFileExists(Assets::directory() . '/' . substr($file, 4));
            }
        }
    }
}
