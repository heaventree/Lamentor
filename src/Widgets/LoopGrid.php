<?php

namespace Lamentor\Widgets;

use Lamentor\Dynamic\DynamicTags;
use Lamentor\Dynamic\LoopSources;
use Lamentor\Elements\Element;
use Lamentor\Models\Document;
use Lamentor\Rendering\Renderer;

/** Original Lamentor widget: repeats a "loop-item" template once per item from a registered data source. */
class LoopGrid extends Widget
{
    public static function name(): string
    {
        return 'loop-grid';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $items = app(LoopSources::class)->items((string) ($settings['items_source'] ?? ''), (int) ($settings['limit'] ?? 6));
        $template = Document::query()->whereKey((int) ($settings['template_id'] ?? 0))->where('type', 'loop-item')->where('status', 'publish')->first();

        $items = is_array($items) ? $items : iterator_to_array($items, false);
        if (! $template || ! $items) {
            return $this->el('div', ['class' => 'lamentor-loop-empty'], e((string) ($settings['empty_text'] ?? '')));
        }

        $tags = app(DynamicTags::class);
        $nodes = array_map(fn (array $n) => Element::fromArray($n), $template->data ?? []);
        $html = '';

        foreach ($items as $item) {
            $html .= $this->el('div', ['class' => 'lamentor-loop-item'], $tags->with($item, fn () => $renderer->renderAll($nodes)));
        }

        // The template's own styles, generated once per grid (scoped by the template's document id).
        $css = $renderer->cssForJson(json_encode($template->data ?? []), $template->id);

        return ($css !== '' ? '<style>' . str_replace('</', '<\/', $css) . '</style>' : '')
            . $this->el('div', ['class' => "lamentor-loop elementor-{$template->id}", 'style' => 'display:grid'], $html);
    }
}
