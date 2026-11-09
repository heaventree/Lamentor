<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

/** Same markup WordPress's [gallery] shortcode produces, built from the gallery control's images. */
class ImageGallery extends Widget
{
    public static function name(): string
    {
        return 'image-gallery';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $images = array_values(array_filter((array) ($settings['wp_gallery'] ?? []), fn ($i) => is_array($i) && ! empty($i['url'])));
        if (! $images) {
            return '';
        }

        if (! empty($settings['gallery_rand'])) {
            shuffle($images);
        }

        $p = $this->prefix();
        $columns = max(1, (int) ($settings['gallery_columns'] ?: 3));
        $size = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings['thumbnail_size'] ?? 'thumbnail')) ?: 'thumbnail';
        $link = (string) ($settings['gallery_link'] ?? 'file');
        $galleryId = 'gallery-' . $this->idInt($element);

        $items = '';
        foreach ($images as $image) {
            $img = $this->imageHtml($image, $size);
            if ($link !== 'none') {
                $img = $this->el('a', ['href' => $image['url'], 'data-elementor-open-lightbox' => 'yes', 'data-elementor-lightbox-slideshow' => $element->id], $img);
            }
            $items .= $this->el('figure', ['class' => 'gallery-item'], $this->el('div', ['class' => 'gallery-icon landscape'], $img));
        }

        $gallery = $this->el('div', [
            'id' => $galleryId,
            'class' => ['gallery', "galleryid-{$this->idInt($element)}", "gallery-columns-{$columns}", "gallery-size-{$size}"],
        ], $items);

        return $this->el('div', ['class' => "{$p}-image-gallery"], $gallery);
    }
}
