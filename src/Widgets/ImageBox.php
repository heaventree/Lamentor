<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

class ImageBox extends Widget
{
    public static function name(): string
    {
        return 'image-box';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $title = $this->safeHtml((string) ($settings['title_text'] ?? ''));
        $description = $this->safeHtml((string) ($settings['description_text'] ?? ''));
        $hasImage = ! empty($settings['image']['url']);
        $hasContent = trim($title) !== '' || trim($description) !== '';

        if (! $hasImage && ! $hasContent) {
            return '';
        }

        $link = new Attributes();
        if (! empty($settings['link']['url'])) {
            $this->addLink($link, 'a', $settings['link']);
        }
        $linkAttrs = $link->string('a');

        $html = '';
        if ($hasImage) {
            $img = $this->imageHtml($settings['image'], (string) ($settings['thumbnail_size'] ?? 'full'));
            if ($linkAttrs !== '') {
                $img = "<a {$linkAttrs} tabindex=\"-1\">{$img}</a>";
            }
            $html .= $this->el('figure', ['class' => "{$p}-image-box-img"], $img);
        }

        if ($hasContent) {
            $content = '';
            if (trim($title) !== '') {
                $titleHtml = $linkAttrs !== '' ? "<a {$linkAttrs}>{$title}</a>" : $title;
                $content .= $this->el($this->tag($settings['title_size'] ?? 'h3', 'h3'), ['class' => "{$p}-image-box-title"], $titleHtml);
            }
            if (trim($description) !== '') {
                $content .= $this->el('p', ['class' => "{$p}-image-box-description"], $description);
            }
            $html .= $this->el('div', ['class' => "{$p}-image-box-content"], $content);
        }

        return $this->el('div', ['class' => "{$p}-image-box-wrapper"], $html);
    }
}
