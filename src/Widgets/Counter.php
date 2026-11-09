<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

/** The number animates client-side (frontend script reads the data-* attributes). */
class Counter extends Widget
{
    public static function name(): string
    {
        return 'counter';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();

        $number = $this->attrs([
            'class' => "{$p}-counter-number",
            'data-duration' => $settings['duration'] ?? null,
            'data-to-value' => $settings['ending_number'] ?? null,
            'data-from-value' => $settings['starting_number'] ?? null,
            'data-delimiter' => ! empty($settings['thousand_separator'])
                ? (empty($settings['thousand_separator_char']) ? ',' : $settings['thousand_separator_char'])
                : null,
        ]);

        $title = '';
        if (! empty($settings['title'])) {
            $tag = $this->tag($settings['title_tag'] ?? 'div');
            $title = $this->el($tag, ['class' => "{$p}-counter-title"], $this->safeHtml((string) $settings['title']));
        }

        $wrapper = $this->el('div', ['class' => "{$p}-counter-number-wrapper"],
            $this->el('span', ['class' => "{$p}-counter-number-prefix"], $this->safeHtml((string) ($settings['prefix'] ?? '')))
            . "<span {$number}>" . $this->safeHtml((string) ($settings['starting_number'] ?? '')) . '</span>'
            . $this->el('span', ['class' => "{$p}-counter-number-suffix"], $this->safeHtml((string) ($settings['suffix'] ?? '')))
        );

        return $this->el('div', ['class' => "{$p}-counter"], $title . $wrapper);
    }
}
