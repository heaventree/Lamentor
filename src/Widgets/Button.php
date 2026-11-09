<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

/** Port of Elementor's button widget (icon support not ported yet). */
class Button extends Widget
{
    public static function name(): string
    {
        return 'button';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        if (($settings['text'] ?? '') === '') {
            return '';
        }

        $p = $this->prefix();
        $a = new Attributes();
        $a->add('button', 'class', "{$p}-button");

        if (! empty($settings['link']['url'])) {
            $this->addLink($a, 'button', $settings['link']);
            $a->add('button', 'class', "{$p}-button-link");
        } else {
            $a->add('button', 'role', 'button');
        }

        if (! empty($settings['button_css_id'])) {
            $a->add('button', 'id', $settings['button_css_id']);
        }

        $a->add('button', 'class', "{$p}-size-" . ($settings['size'] ?: 'sm'));

        if (! empty($settings['hover_animation'])) {
            $a->add('button', 'class', "{$p}-animation-" . $settings['hover_animation']);
        }

        return view('lamentor::widgets.button', [
            'p' => $p,
            'attributes' => $a->string('button'),
            'text' => $this->safeHtml((string) $settings['text']),
        ])->render();
    }
}
