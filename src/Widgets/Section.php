<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Schema\SchemaRepository;

/**
 * Legacy section (the pre-container layout element); still used by many saved
 * documents. Shape dividers are not ported yet.
 */
class Section extends Widget
{
    private const TAGS = ['div', 'header', 'footer', 'main', 'article', 'section', 'aside', 'nav'];

    public static function name(): string
    {
        return 'section';
    }

    public function controls(): array
    {
        return app(SchemaRepository::class)->elementControls('section');
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        return $renderer->renderChildren($element);
    }

    public function renderElement(Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $settings = $this->activeSettings($this->settingsFor($element));

        $tag = in_array($settings['html_tag'] ?? 'section', self::TAGS, true) ? $settings['html_tag'] : 'section';
        $attrs = $this->wrapperAttributes($element, $settings, ["{$p}-section", $element->isInner ? "{$p}-inner-section" : "{$p}-top-section"]);

        $html = '';

        if (($settings['background_background'] ?? '') === 'video' && ! empty($settings['background_video_link'])) {
            $video = (($settings['background_play_once'] ?? '') === 'yes' ? '' : ' loop') ? ' autoplay muted playsinline loop' : ' autoplay muted playsinline';
            $isEmbed = (bool) preg_match('/(youtu\.be|youtube\.com|vimeo\.com)/i', (string) $settings['background_video_link']);
            $html .= $this->el('div', [
                'class' => ["{$p}-background-video-container", empty($settings['background_play_on_mobile']) ? "{$p}-hidden-mobile" : null],
            ], $isEmbed
                ? "<div class=\"{$p}-background-video-embed\" role=\"presentation\"></div>"
                : "<video class=\"{$p}-background-video-hosted\" role=\"presentation\"{$video}></video>");
        }

        $overlay = [$settings['background_overlay_background'] ?? '', $settings['background_overlay_hover_background'] ?? ''];
        if (array_intersect($overlay, ['classic', 'gradient'])) {
            $html .= "<div class=\"{$p}-background-overlay\"></div>";
        }

        $gap = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings['gap'] ?? 'default'));
        $html .= $this->el('div', ['class' => ["{$p}-container", "{$p}-column-gap-{$gap}"]], $this->render($settings, $element, $renderer));

        return "<{$tag} " . $attrs->string('_wrapper') . ">{$html}</{$tag}>";
    }
}
