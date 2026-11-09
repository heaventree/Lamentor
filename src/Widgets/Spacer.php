<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

class Spacer extends Widget
{
    public static function name(): string
    {
        return 'spacer';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $size = $settings['space']['size'] ?? '';

        // Same as Elementor: render nothing when the size is empty or zero.
        if ($size === '' || (float) $size === 0.0) {
            return '';
        }

        $p = $this->prefix();

        return "<div class=\"{$p}-spacer\"><div class=\"{$p}-spacer-inner\"></div></div>";
    }
}
