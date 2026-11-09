<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

/**
 * Port of Elementor's image widget. Images are addressed by URL: there is no
 * attachment/size system, so `image_size` only drives the CSS class names.
 */
class Image extends Widget
{
    public static function name(): string
    {
        return 'image';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $image = $settings['image'] ?? [];
        $url = (string) ($image['url'] ?? '');

        if ($url === '' || preg_match('/^(javascript|vbscript):/i', preg_replace('/[\x00-\x20]+/', '', $url))) {
            return '';
        }

        $p = $this->prefix();
        $size = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings['image_size'] ?: 'large'));

        $img = new Attributes();
        $img->add('img', 'src', $url);
        $img->add('img', 'alt', (string) ($image['alt'] ?? ''));
        $classes = ["attachment-{$size}", "size-{$size}"];
        if (! empty($image['id'])) {
            $classes[] = 'wp-image-' . (int) $image['id'];
        }
        if (! empty($settings['hover_animation'])) {
            $classes[] = "{$p}-animation-" . $settings['hover_animation'];
        }
        $img->add('img', 'class', $classes);
        $html = '<img ' . $img->string('img') . '>';

        $link = $this->linkFor($settings);
        if ($link) {
            $a = new Attributes();
            $this->addLink($a, 'link', $link);
            if ($settings['link_to'] !== 'custom') {
                $a->add('link', 'data-elementor-open-lightbox', $settings['open_lightbox'] ?: 'default');
            }
            if ($a->string('link') !== '') {
                $html = '<a ' . $a->string('link') . '>' . $html . '</a>';
            }
        }

        $caption = $this->captionFor($settings);
        if ($caption !== '') {
            $html = '<figure class="wp-caption">' . $html
                . '<figcaption class="widget-image-caption wp-caption-text">' . $this->safeHtml($caption) . '</figcaption></figure>';
        }

        return $html;
    }

    private function linkFor(array $settings): ?array
    {
        return match ($settings['link_to']) {
            'custom' => ! empty($settings['link']['url']) ? $settings['link'] : null,
            'file' => ['url' => $settings['image']['url']],
            default => null,
        };
    }

    /** Attachment captions need a media library; only custom captions render. */
    private function captionFor(array $settings): string
    {
        return $settings['caption_source'] === 'custom' ? (string) ($settings['caption'] ?? '') : '';
    }
}
