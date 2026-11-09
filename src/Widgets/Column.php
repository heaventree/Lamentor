<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Schema\SchemaRepository;

/** Legacy column inside a section. */
class Column extends Widget
{
    private const TAGS = ['div', 'header', 'footer', 'main', 'article', 'section', 'aside', 'nav'];

    public static function name(): string
    {
        return 'column';
    }

    public function controls(): array
    {
        return app(SchemaRepository::class)->elementControls('column');
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        return $renderer->renderChildren($element);
    }

    public function renderElement(Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $settings = $this->activeSettings($this->settingsFor($element));

        $tag = in_array($settings['html_tag'] ?? 'div', self::TAGS, true) ? $settings['html_tag'] : 'div';
        $size = preg_replace('/[^0-9a-z_-]/i', '', (string) ($settings['_column_size'] ?? 100));
        $attrs = $this->wrapperAttributes($element, $settings, [
            "{$p}-column",
            "{$p}-col-{$size}",
            $element->isInner ? "{$p}-inner-column" : "{$p}-top-column",
        ]);

        $overlay = [$settings['background_overlay_background'] ?? '', $settings['background_overlay_hover_background'] ?? ''];
        $inner = array_intersect($overlay, ['classic', 'gradient']) ? "<div class=\"{$p}-background-overlay\"></div>" : '';
        $inner .= $this->render($settings, $element, $renderer);

        $wrap = $this->el('div', ['class' => ["{$p}-widget-wrap", $element->children ? "{$p}-element-populated" : null]], $inner);

        return "<{$tag} " . $attrs->string('_wrapper') . ">{$wrap}</{$tag}>";
    }
}
