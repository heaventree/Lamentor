<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class IconList extends Widget
{
    public static function name(): string
    {
        return 'icon-list';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $inline = ($settings['view'] ?? '') === 'inline';

        $listClass = ["{$p}-icon-list-items", $inline ? "{$p}-inline-items" : null];
        $itemClass = ["{$p}-icon-list-item", $inline ? "{$p}-inline-item" : null];

        $html = '';
        foreach ($this->items('icon_list', $settings) as $item) {
            $inner = '';

            $icon = Icons::render($item['selected_icon'] ?? []);
            if ($icon !== '') {
                $inner .= $this->el('span', ['class' => "{$p}-icon-list-icon"], $icon);
            }
            $inner .= $this->el('span', ['class' => "{$p}-icon-list-text"], $this->safeHtml((string) ($item['text'] ?? '')));

            if (! empty($item['link']['url'])) {
                $a = new Attributes();
                $this->addLink($a, 'a', $item['link']);
                if ($a->string('a') !== '') {
                    $inner = '<a ' . $a->string('a') . '>' . $inner . '</a>';
                }
            }

            $html .= $this->el('li', ['class' => $itemClass], $inner);
        }

        return $this->el('ul', ['class' => $listClass], $html);
    }
}
