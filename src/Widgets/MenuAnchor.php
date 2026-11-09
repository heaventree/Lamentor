<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

class MenuAnchor extends Widget
{
    public static function name(): string
    {
        return 'menu-anchor';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $anchor = (string) ($settings['anchor'] ?? '');
        if ($anchor === '') {
            return '';
        }

        // Same as WordPress's sanitize_html_class().
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', preg_replace('/%[a-fA-F0-9][a-fA-F0-9]/', '', $anchor));

        return $this->el('div', ['class' => $this->prefix() . '-menu-anchor', 'id' => $id]);
    }
}
