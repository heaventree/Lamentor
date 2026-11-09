<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;

class Testimonial extends Widget
{
    public static function name(): string
    {
        return 'testimonial';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $content = (string) ($settings['testimonial_content'] ?? '');
        $name = (string) ($settings['testimonial_name'] ?? '');
        $job = (string) ($settings['testimonial_job'] ?? '');
        $hasImage = ! empty($settings['testimonial_image']['url']);

        if ($content === '' && $name === '' && $job === '' && ! $hasImage) {
            return '';
        }

        $link = new Attributes();
        if (! empty($settings['link']['url'])) {
            $this->addLink($link, 'a', $settings['link']);
        }
        $linkAttrs = $link->string('a');

        $html = '';
        if ($content !== '') {
            $html .= $this->el('div', ['class' => "{$p}-testimonial-content"], $this->safeHtml($content));
        }

        if ($hasImage || $name !== '' || $job !== '') {
            $inner = '';

            if ($hasImage) {
                $img = $this->imageHtml($settings['testimonial_image'], (string) ($settings['testimonial_image_size'] ?? 'full'));
                if ($linkAttrs !== '') {
                    $img = "<a {$linkAttrs}>{$img}</a>";
                }
                $inner .= $this->el('div', ['class' => "{$p}-testimonial-image"], $img);
            }

            if ($name !== '' || $job !== '') {
                $details = '';
                foreach (['name' => $name, 'job' => $job] as $key => $text) {
                    if ($text === '') {
                        continue;
                    }
                    $class = "{$p}-testimonial-{$key}";
                    $details .= $linkAttrs !== ''
                        ? "<a class=\"{$class}\" {$linkAttrs}>" . $this->safeHtml($text) . '</a>'
                        : $this->el('div', ['class' => $class], $this->safeHtml($text));
                }
                $inner .= $this->el('div', ['class' => "{$p}-testimonial-details"], $details);
            }

            $meta = $this->el('div', ['class' => "{$p}-testimonial-meta-inner"], $inner);
            $html .= $this->el('div', [
                'class' => [
                    "{$p}-testimonial-meta",
                    $hasImage ? "{$p}-has-image" : null,
                    ! empty($settings['testimonial_image_position']) ? "{$p}-testimonial-image-position-" . $settings['testimonial_image_position'] : null,
                ],
            ], $meta);
        }

        return $this->el('div', ['class' => "{$p}-testimonial-wrapper"], $html);
    }
}
