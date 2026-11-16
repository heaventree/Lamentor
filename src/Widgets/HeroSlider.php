<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

/**
 * Full-width banner slider: each slide has a background image, a headline, text and a button,
 * and every slide animates its text in. Lamentor's own widget (not part of Elementor core): the
 * markup is plain HTML, the sliding is done by js/lamentor-hero-slider.js and the look by
 * css/lamentor-hero-slider.css, with no library.
 */
class HeroSlider extends Widget
{
    public static function name(): string
    {
        return 'hero-slider';
    }

    private const ANIMATIONS = ['fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'fadeIn', 'none'];

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $slides = array_values(array_filter(
            $this->items('slides', $settings),
            fn (array $s) => trim((string) ($s['title'] ?? '')) !== '' || trim((string) ($s['text'] ?? '')) !== '' || ! empty($s['image']['url']),
        ));
        if (! $slides) {
            return '';
        }

        $effect = ($settings['effect'] ?? 'fade') === 'slide' ? 'slide' : 'fade';
        $html = '';
        foreach ($slides as $i => $slide) {
            $animation = in_array($slide['animation'] ?? '', self::ANIMATIONS, true) ? $slide['animation'] : 'fadeInUp';
            $content = '';

            $title = trim((string) ($slide['title'] ?? ''));
            if ($title !== '') {
                $content .= $this->el($i === 0 ? 'h1' : 'h2', ['class' => ['lm-hero-title', 'lm-a-' . $animation], 'style' => '--lm-d:.2s'], $this->safeHtml($title));
            }
            $text = trim((string) ($slide['text'] ?? ''));
            if ($text !== '') {
                $content .= $this->el('p', ['class' => ['lm-hero-text', 'lm-a-' . $animation], 'style' => '--lm-d:.45s'], $this->safeHtml($text));
            }
            $buttonText = trim((string) ($slide['button_text'] ?? ''));
            if ($buttonText !== '') {
                $link = new Attributes();
                $this->addLink($link, 'a', (array) ($slide['link'] ?? []));
                $href = $link->string('a');
                $content .= '<a class="lm-hero-button lm-a-' . $animation . '" style="--lm-d:.7s" ' . ($href !== '' ? $href : 'href="#"') . '>' . $this->safeHtml($buttonText) . '</a>';
            }

            $image = ! empty($slide['image']['url']) ? $this->imageHtml((array) $slide['image'], 'full', ['lm-hero-bg'], $i === 0 ? [] : ['loading' => 'lazy']) : '';

            $html .= $this->el('div', [
                'class' => ['lm-hero-slide', $i === 0 ? 'is-active' : null],
                'role' => 'group',
                'aria-roledescription' => 'slide',
                'aria-label' => ($i + 1) . ' / ' . count($slides),
            ], $image . '<div class="lm-hero-content">' . $content . '</div>');
        }

        $controls = '';
        if (count($slides) > 1) {
            if (($settings['arrows'] ?? 'yes') === 'yes') {
                $controls .= '<button type="button" class="lm-hero-arrow lm-hero-prev" aria-label="Previous slide"><span aria-hidden="true">&#8249;</span></button>'
                    . '<button type="button" class="lm-hero-arrow lm-hero-next" aria-label="Next slide"><span aria-hidden="true">&#8250;</span></button>';
            }
            if (($settings['dots'] ?? 'yes') === 'yes') {
                $controls .= '<div class="lm-hero-dots">';
                foreach ($slides as $i => $_) {
                    $controls .= '<button type="button" class="lm-hero-dot' . ($i === 0 ? ' is-active' : '') . '" aria-label="Go to slide ' . ($i + 1) . '"></button>';
                }
                $controls .= '</div>';
            }
        }

        return $this->el('div', [
            'class' => ['lm-hero-slider', "lm-effect-{$effect}"],
            'role' => 'region',
            'aria-roledescription' => 'carousel',
            'aria-label' => 'Banner',
        ], $html . $controls);
    }
}
