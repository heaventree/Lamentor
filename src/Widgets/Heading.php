<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

class Heading extends Widget
{
    private const TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p'];

    public static function name(): string
    {
        return 'heading';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        if (($settings['title'] ?? '') === '') {
            return '';
        }

        $p = $this->prefix();
        $size = ! empty($settings['size']) ? $settings['size'] : 'default';
        $tag = in_array($settings['header_size'] ?? 'h2', self::TAGS, true) ? $settings['header_size'] : 'h2';

        $title = $this->safeHtml((string) $settings['title']);
        $link = $settings['link'] ?? [];

        $a = new Attributes();
        $this->addLink($a, 'a', $link);
        if ($a->string('a') !== '') {
            $title = '<a ' . $a->string('a') . '>' . $title . '</a>';
        }

        return view('lamentor::widgets.heading', [
            'tag' => $tag,
            'class' => "{$p}-heading-title {$p}-size-{$size}",
            'title' => $title,
        ])->render();
    }
}
