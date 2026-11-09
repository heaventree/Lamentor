<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class Rating extends Widget
{
    public static function name(): string
    {
        return 'rating';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $scale = (int) ($settings['rating_scale']['size'] ?? 5);
        $raw = $settings['rating_value'] ?? '';
        $value = round((float) ($raw === '' ? $scale : $raw), 2);
        $shown = $value == (int) $value ? (int) $value : $value;

        $icon = Icons::render($settings['rating_icon'] ?? []);

        $icons = '';
        for ($i = 1; $i <= $scale; $i++) {
            $width = '0%';
            if ($value >= $i) {
                $width = '100%';
            } elseif ((int) ceil($value) === $i) {
                $width = (($value - ($i - 1)) * 100) . '%';
            }

            $icons .= '<div class="e-icon">'
                . $this->el('div', ['class' => 'e-icon-wrapper e-icon-marked', 'style' => $width !== '100%' ? "--e-rating-icon-marked-width: {$width};" : null], $icon)
                . '<div class="e-icon-wrapper e-icon-unmarked">' . $icon . '</div></div>';
        }

        $wrapper = $this->el('div', [
            'class' => 'e-rating-wrapper',
            'itemprop' => 'ratingValue',
            'content' => (string) $shown,
            'role' => 'img',
            'aria-label' => "Rated {$shown} out of {$scale}",
        ], $icons);

        return '<div class="e-rating" itemtype="https://schema.org/Rating" itemscope="" itemprop="reviewRating">'
            . '<meta itemprop="worstRating" content="0"><meta itemprop="bestRating" content="' . $scale . '">'
            . $wrapper . '</div>';
    }
}
