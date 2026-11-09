<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class Divider extends Widget
{
    public static function name(): string
    {
        return 'divider';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $look = $settings['look'] ?? '';
        $icon = $look === 'line_icon' ? Icons::render($settings['icon'] ?? []) : '';
        $text = $look === 'line_text' ? (string) ($settings['text'] ?? '') : '';

        $style = '';
        $svg = $this->patternSvg($settings);
        if ($svg !== '') {
            $uri = str_replace(['<', '>', '"', '#'], ['%3C', '%3E', "'", '%23'], $svg);
            $style = '--divider-pattern-url: url("data:image/svg+xml,' . $uri . '");';
        }

        $inner = '';
        if ($icon !== '') {
            $inner = $this->el('div', ['class' => ["{$p}-icon", "{$p}-divider__element"]], $icon);
        } elseif (trim($text) !== '') {
            $inner = $this->el($this->tag($settings['html_tag'] ?? 'span', 'span'), ['class' => ["{$p}-divider__text", "{$p}-divider__element"]], $this->safeHtml($text));
        }

        return $this->el('div', ['class' => "{$p}-divider", 'style' => $style ?: null],
            $this->el('span', ['class' => "{$p}-divider-separator"], $inner));
    }

    private function patternSvg(array $settings): string
    {
        if (($settings['separator_type'] ?? '') !== 'pattern' || empty($settings['style'])) {
            return '';
        }

        $path = __DIR__ . '/../../resources/schema/divider-styles.json';
        $styles = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $pattern = $styles[$settings['style']] ?? null;
        if (! $pattern) {
            return '';
        }

        $attr = [
            'preserveAspectRatio' => ! empty($pattern['preserve_aspect_ratio']) ? 'xMidYMid meet' : 'none',
            'overflow' => 'visible',
            'height' => '100%',
            'viewBox' => $pattern['view_box'] ?? '0 0 24 24',
        ];

        if (($pattern['group'] ?? '') !== 'line') {
            $attr += ['fill' => 'black', 'stroke' => 'none'];
        } else {
            $attr += [
                'fill' => 'none',
                'stroke' => 'black',
                'stroke-width' => (string) ($settings['weight']['size'] ?? 1),
                'stroke-linecap' => 'square',
                'stroke-miterlimit' => '10',
            ];
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" ' . $this->attrs($attr) . '>' . $pattern['shape'] . '</svg>';
    }
}
