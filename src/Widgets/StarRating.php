<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

class StarRating extends Widget
{
    public static function name(): string
    {
        return 'star-rating';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $scale = (int) ($settings['rating_scale'] ?? 5);
        $rating = min((float) ($settings['rating'] ?? 5), $scale);
        $floored = floor($rating);

        $icon = '&#xE934;';
        if (($settings['star_style'] ?? '') === 'star_fontawesome') {
            if (($settings['unmarked_star_style'] ?? '') === 'outline') {
                $icon = '&#xE933;';
            }
        } elseif (($settings['star_style'] ?? '') === 'star_unicode') {
            $icon = ($settings['unmarked_star_style'] ?? '') === 'outline' ? '&#9734;' : '&#9733;';
        }

        $stars = '';
        for ($i = 1.0; $i <= $scale; $i++) {
            if ($i <= $floored) {
                $class = "{$p}-star-full";
            } elseif ($floored + 1 === $i && $rating !== $floored) {
                $class = "{$p}-star-" . ($rating - $floored) * 10;
            } else {
                $class = "{$p}-star-empty";
            }
            $stars .= "<i class=\"{$class}\" aria-hidden=\"true\">{$icon}</i>";
        }

        $shown = $rating == (int) $rating ? (int) $rating : $rating;
        $text = e("Rated {$shown} out of {$scale}");

        $title = trim((string) ($settings['title'] ?? '')) !== ''
            ? $this->el('div', ['class' => "{$p}-star-rating__title"], e($settings['title']))
            : '';

        $wrapper = '<div class="' . "{$p}-star-rating" . '" itemtype="http://schema.org/Rating" itemscope="" itemprop="reviewRating">'
            . $stars . "<span itemprop=\"ratingValue\" class=\"{$p}-screen-only\">{$text}</span></div>";

        return $this->el('div', ['class' => "{$p}-star-rating__wrapper"], $title . $wrapper);
    }
}
