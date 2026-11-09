<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

class IconBox extends Widget
{
    public static function name(): string
    {
        return 'icon-box';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $hasLink = ! empty($settings['link']['url']);
        $icon = Icons::render($settings['selected_icon'] ?? []);
        $title = (string) ($settings['title_text'] ?? '');
        $description = (string) ($settings['description_text'] ?? '');
        $hasContent = trim($title) !== '' || trim($description) !== '';

        if ($icon === '' && ! $hasContent) {
            return '';
        }

        $link = new Attributes();
        if ($hasLink) {
            $this->addLink($link, 'link', $settings['link']);
        }
        $tag = str_contains($link->string('link'), 'href=') ? 'a' : 'span';

        $out = "<div class=\"{$p}-icon-box-wrapper\">";

        if ($icon !== '') {
            $a = new Attributes();
            $a->add('icon', 'class', "{$p}-icon");
            if (! empty($settings['hover_animation'])) {
                $a->add('icon', 'class', "{$p}-animation-" . $settings['hover_animation']);
            }
            if ($tag === 'a') {
                $a->add('icon', 'tabindex', '-1');
                if (trim($title) !== '') {
                    $a->add('icon', 'aria-label', strip_tags($title));
                }
            }
            $out .= "<div class=\"{$p}-icon-box-icon\"><{$tag} " . trim($link->string('link') . ' ' . $a->string('icon')) . ">{$icon}</{$tag}></div>";
        }

        if ($hasContent) {
            $out .= "<div class=\"{$p}-icon-box-content\">";
            if (trim($title) !== '') {
                $h = $this->tag($settings['title_size'] ?? 'h3', 'h3');
                $out .= "<{$h} class=\"{$p}-icon-box-title\"><{$tag} " . $link->string('link') . '>'
                    . $this->safeHtml($title) . "</{$tag}></{$h}>";
            }
            if (trim($description) !== '') {
                $out .= "<p class=\"{$p}-icon-box-description\">" . $this->safeHtml($description) . '</p>';
            }
            $out .= '</div>';
        }

        return $out . '</div>';
    }
}
