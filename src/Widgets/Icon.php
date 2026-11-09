<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class Icon extends Widget
{
    public static function name(): string
    {
        return 'icon';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $icon = Icons::render($settings['selected_icon'] ?? []);
        if ($icon === '') {
            return '';
        }

        $p = $this->prefix();
        $a = new Attributes();
        $a->add('icon', 'class', "{$p}-icon");
        if (! empty($settings['hover_animation'])) {
            $a->add('icon', 'class', "{$p}-animation-" . $settings['hover_animation']);
        }

        $tag = 'div';
        if (! empty($settings['link']['url'])) {
            $this->addLink($a, 'icon', $settings['link']);
            if (str_contains($a->string('icon'), 'href=')) {
                $tag = 'a';
            }
        }

        return "<div class=\"{$p}-icon-wrapper\"><{$tag} " . $a->string('icon') . ">{$icon}</{$tag}></div>";
    }
}
