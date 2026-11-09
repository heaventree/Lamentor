<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

/**
 * The "html" widget: prints author-supplied markup verbatim, as Elementor does.
 * It is intentionally NOT sanitized (scripts are a feature), so only trusted
 * users may be allowed to create documents containing it.
 */
class RawHtml extends Widget
{
    public static function name(): string
    {
        return 'html';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        return (string) ($settings['html'] ?? '');
    }
}
