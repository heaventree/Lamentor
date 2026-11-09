<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Icons;

/** Opening/closing is done client-side by Elementor's frontend script. */
class Accordion extends Widget
{
    /** "accordion" or "toggle": the two widgets differ only in these names. */
    protected string $kind = 'accordion';

    public static function name(): string
    {
        return 'accordion';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $kind = $this->kind;
        $id = $this->idInt($element);
        $items = $this->items('tabs', $settings);
        $tag = $this->tag($settings['title_html_tag'] ?? 'div');

        $closed = Icons::render($settings['selected_icon'] ?? [], false);
        $opened = Icons::render($settings['selected_active_icon'] ?? [], false);
        $iconAlign = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings['icon_align'] ?? 'left'));

        $html = '';
        foreach ($items as $index => $item) {
            $n = $index + 1;

            $icon = '';
            if ($closed !== '') {
                $icon = $this->el('span', ['class' => ["{$p}-{$kind}-icon", "{$p}-{$kind}-icon-{$iconAlign}"], 'aria-hidden' => 'true'],
                    $this->el('span', ['class' => "{$p}-{$kind}-icon-closed"], $closed)
                    . $this->el('span', ['class' => "{$p}-{$kind}-icon-opened"], $opened)
                );
            }

            $title = $this->el($tag, [
                'id' => "{$p}-tab-title-{$id}{$n}",
                'class' => "{$p}-tab-title",
                'data-tab' => (string) $n,
                'role' => 'button',
                'aria-controls' => "{$p}-tab-content-{$id}{$n}",
                'aria-expanded' => 'false',
            ], $icon . $this->el('a', ['class' => "{$p}-{$kind}-title", 'tabindex' => '0'], $this->safeHtml((string) ($item['tab_title'] ?? ''))));

            $content = $this->el('div', [
                'id' => "{$p}-tab-content-{$id}{$n}",
                'class' => ["{$p}-tab-content", "{$p}-clearfix"],
                'data-tab' => (string) $n,
                'role' => 'region',
                'aria-labelledby' => "{$p}-tab-title-{$id}{$n}",
            ], $this->richHtml((string) ($item['tab_content'] ?? '')));

            $html .= $this->el('div', ['class' => "{$p}-{$kind}-item"], $title . $content);
        }

        if (($settings['faq_schema'] ?? '') === 'yes') {
            $html .= $this->faqSchema($items);
        }

        return $this->el('div', ['class' => "{$p}-{$kind}"], $html);
    }

    /** @param list<array<string,mixed>> $items */
    private function faqSchema(array $items): string
    {
        $entities = [];
        foreach ($items as $item) {
            $entities[] = [
                '@type' => 'Question',
                'name' => strip_tags((string) ($item['tab_title'] ?? '')),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $this->richHtml((string) ($item['tab_content'] ?? ''))],
            ];
        }

        $json = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
