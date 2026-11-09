<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

/** Port of Elementor's text-editor widget (drop cap not ported yet). */
class TextEditor extends Widget
{
    public static function name(): string
    {
        return 'text-editor';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        return $this->richHtml((string) ($settings['editor'] ?? ''));
    }
}
