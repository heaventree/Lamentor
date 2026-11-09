<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

/** Swiper markup; the sliding itself is done by Elementor's frontend script. */
class ImageCarousel extends Widget
{
    public static function name(): string
    {
        return 'image-carousel';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $slides = array_values(array_filter((array) ($settings['carousel'] ?? []), fn ($s) => is_array($s) && ! empty($s['url'])));
        if (! $slides) {
            return '';
        }

        $p = $this->prefix();
        $lazy = ($settings['lazyload'] ?? '') === 'yes';
        $count = count($slides);
        $linkTo = (string) ($settings['link_to'] ?? 'none');

        $html = '';
        foreach ($slides as $i => $image) {
            $alt = e((string) ($image['alt'] ?? ''));
            $url = e($image['url']);
            $img = $lazy
                ? "<img class=\"swiper-slide-image swiper-lazy\" data-src=\"{$url}\" alt=\"{$alt}\" />"
                : "<img class=\"swiper-slide-image\" src=\"{$url}\" alt=\"{$alt}\" />";

            $link = null;
            if ($linkTo === 'file') {
                $link = ['url' => $image['url']];
            } elseif ($linkTo === 'custom' && ! empty($settings['link']['url'])) {
                $link = $settings['link'];
            }

            $open = $close = '';
            if ($link) {
                $a = new Attributes();
                $this->addLink($a, 'a', $link);
                if ($linkTo !== 'custom') {
                    $a->add('a', 'data-elementor-open-lightbox', $settings['open_lightbox'] ?? 'default');
                    $a->add('a', 'data-elementor-lightbox-slideshow', $element->id);
                }
                if ($a->string('a') !== '') {
                    $open = '<a ' . $a->string('a') . '>';
                    $close = '</a>';
                }
            }

            $caption = ! empty($settings['caption_type']) && ! empty($image['caption'])
                ? $this->el('figcaption', ['class' => "{$p}-image-carousel-caption"], $this->safeHtml((string) $image['caption']))
                : '';

            $html .= $this->el('div', [
                'class' => 'swiper-slide',
                'role' => 'group',
                'aria-roledescription' => 'slide',
                'aria-label' => ($i + 1) . " of {$count}",
            ], $open . '<figure class="swiper-slide-inner">' . $img . ($lazy ? '<div class="swiper-lazy-preloader"></div>' : '') . $caption . '</figure>' . $close);
        }

        $navigation = (string) ($settings['navigation'] ?? 'both');
        $arrows = in_array($navigation, ['arrows', 'both'], true);
        $dots = in_array($navigation, ['dots', 'both'], true);
        $autoplay = ($settings['autoplay'] ?? '') === 'yes';

        $track = $this->el('div', [
            'class' => ["{$p}-image-carousel", 'swiper-wrapper', ($settings['image_stretch'] ?? '') === 'yes' ? 'swiper-image-stretch' : null],
            'aria-live' => $autoplay ? 'off' : 'polite',
        ], $html);

        $controls = '';
        if ($count > 1) {
            if ($arrows) {
                $prev = '<svg aria-hidden="true" class="e-font-icon-svg e-eicon-chevron-left" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d="M646 125C629 125 613 133 604 142L308 442C296 454 292 471 292 487 292 504 296 521 308 533L604 854C617 867 629 875 646 875 663 875 679 871 692 858 704 846 713 829 713 812 713 796 708 779 692 767L438 487 692 225C700 217 708 204 708 187 708 171 704 154 692 142 675 129 663 125 646 125Z"/></svg>';
                $next = '<svg aria-hidden="true" class="e-font-icon-svg e-eicon-chevron-right" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d="M696 533C708 521 713 504 713 487 713 471 708 454 696 446L400 146C388 133 375 125 354 125 338 125 325 129 313 142 300 154 292 171 292 187 292 204 296 221 308 233L563 492 304 771C292 783 288 800 288 817 288 833 296 850 308 863 321 871 338 875 354 875 371 875 388 867 400 854L696 533Z"/></svg>';
                $controls .= "<div class=\"{$p}-swiper-button {$p}-swiper-button-prev\" role=\"button\" tabindex=\"0\">{$prev}</div>"
                    . "<div class=\"{$p}-swiper-button {$p}-swiper-button-next\" role=\"button\" tabindex=\"0\">{$next}</div>";
            }
            if ($dots) {
                $controls .= '<div class="swiper-pagination"></div>';
            }
        }

        return $this->el('div', [
            'class' => ["{$p}-image-carousel-wrapper", 'swiper'],
            'role' => 'region',
            'aria-roledescription' => 'carousel',
            'aria-label' => $settings['carousel_name'] ?? null,
            'dir' => $settings['direction'] ?? null,
        ], $track . $controls);
    }
}
