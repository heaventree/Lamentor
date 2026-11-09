<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class SocialIcons extends Widget
{
    public static function name(): string
    {
        return 'social-icons';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $items = $this->items('social_icon_list', $settings);
        $multiple = count($items) > 1;
        $animation = ! empty($settings['hover_animation']) ? " {$p}-animation-" . $settings['hover_animation'] : '';

        $html = '';
        foreach ($items as $item) {
            $icon = $item['social_icon'] ?? [];
            $social = '';

            // "fab fa-facebook" -> "facebook"
            if (($icon['library'] ?? '') !== 'svg' && is_string($icon['value'] ?? null)) {
                $parts = explode(' ', $icon['value'], 2);
                $social = isset($parts[1]) ? str_replace('fa-', '', $parts[1]) : '';
            }
            $social = preg_replace('/[^A-Za-z0-9_-]/', '', $social);

            $a = new Attributes();
            $a->add('a', 'class', ["{$p}-icon", "{$p}-social-icon", "{$p}-social-icon-{$social}{$animation}", "{$p}-repeater-item-" . ($item['_id'] ?? '')]);
            $this->addLink($a, 'a', $item['link'] ?? []);

            $link = '<a ' . $a->string('a') . '>'
                . $this->el('span', ['class' => "{$p}-screen-only"], e(ucwords($social)))
                . Icons::render($icon) . '</a>';

            $html .= $this->el('span', ['class' => "{$p}-grid-item", 'role' => $multiple ? 'listitem' : null], $link);
        }

        return $this->el('div', ['class' => ["{$p}-social-icons-wrapper", "{$p}-grid"], 'role' => $multiple ? 'list' : null], $html);
    }
}
