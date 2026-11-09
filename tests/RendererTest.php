<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Orchestra\Testbench\TestCase;

class RendererTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    public function test_renders_heading_inside_container(): void
    {
        $json = json_encode([[
            'id' => 'abc12345',
            'elType' => 'container',
            'settings' => [],
            'elements' => [[
                'id' => 'def67890',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => 'Hello', 'header_size' => 'h1'],
                'elements' => [],
            ]],
            'isInner' => false,
        ]]);

        $html = $this->app->make(Renderer::class)->renderJson($json);

        $this->assertStringContainsString('data-id="abc12345"', $html);
        $this->assertStringContainsString('e-con-boxed', $html);
        $this->assertStringContainsString('elementor-widget-heading', $html);
        $this->assertStringContainsString('data-widget_type="heading.default"', $html);
        $this->assertMatchesRegularExpression('#<h1 class="elementor-heading-title elementor-size-default">Hello</h1>#', $html);
    }

    public function test_unknown_widget_renders_nothing(): void
    {
        $json = json_encode([['id' => 'x', 'elType' => 'widget', 'widgetType' => 'nope', 'settings' => [], 'elements' => []]]);

        $this->assertSame('', $this->app->make(Renderer::class)->renderJson($json));
    }
}
