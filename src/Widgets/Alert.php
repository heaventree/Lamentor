<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class Alert extends Widget
{
    public static function name(): string
    {
        return 'alert';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $title = (string) ($settings['alert_title'] ?? '');
        $description = (string) ($settings['alert_description'] ?? '');

        if (trim($title) === '' && trim($description) === '') {
            return '';
        }

        $p = $this->prefix();
        $inner = '';

        if (trim($title) !== '') {
            $inner .= $this->el('span', ['class' => "{$p}-alert-title"], $this->safeHtml($title));
        }
        if (trim($description) !== '') {
            $inner .= $this->el('span', ['class' => "{$p}-alert-description"], $this->safeHtml($description));
        }
        if (($settings['show_dismiss'] ?? '') === 'show') {
            $icon = Icons::render($settings['dismiss_icon'] ?? []) ?: '<span aria-hidden="true">&times;</span>';
            $inner .= $this->el('button', ['type' => 'button', 'class' => "{$p}-alert-dismiss", 'aria-label' => 'Dismiss this alert.'], $icon);
        }

        return $this->el('div', ['class' => "{$p}-alert", 'role' => 'alert'], $inner);
    }
}
