<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

class Progress extends Widget
{
    public static function name(): string
    {
        return 'progress';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $title = (string) ($settings['title'] ?? '');
        $size = $settings['percent']['size'] ?? '';

        if ($title === '' && ($size === '' || $size === null || (float) $size === 0.0)) {
            return '';
        }

        $p = $this->prefix();
        $barId = "{$p}-progress-bar-" . $element->id;
        $percent = is_numeric($size) ? min((float) $size, 100) : 0;
        $percent = $percent == (int) $percent ? (int) $percent : $percent;

        $wrapper = [
            'class' => ["{$p}-progress-wrapper", ! empty($settings['progress_type']) ? 'progress-' . $settings['progress_type'] : null],
            'role' => 'progressbar',
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
            'aria-valuenow' => (string) $percent,
        ];

        $titleHtml = '';
        if (trim($title) !== '') {
            if (($settings['title_display'] ?? '') === 'yes') {
                $tag = $this->tag($settings['title_tag'] ?? 'span', 'span');
                $titleHtml = $this->el($tag, ['class' => "{$p}-title", 'id' => $barId], $this->safeHtml($title));
                $wrapper['aria-labelledby'] = $barId;
            } else {
                $wrapper['aria-label'] = strip_tags($title);
            }
        }

        $inner = (string) ($settings['inner_text'] ?? '');
        if ($inner !== '') {
            $wrapper['aria-valuetext'] = "{$percent}% (" . strip_tags($inner) . ')';
        }

        $text = $this->el('span', ['class' => "{$p}-progress-text"], $this->safeHtml($inner));
        if (($settings['display_percentage'] ?? '') === 'show') {
            $text .= $this->el('span', ['class' => "{$p}-progress-percentage"], $percent . '%');
        }

        $bar = $this->el('div', ['class' => "{$p}-progress-bar", 'data-max' => (string) $percent], $text);

        return $titleHtml . $this->el('div', $wrapper, $bar);
    }
}
