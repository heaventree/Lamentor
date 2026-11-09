<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

/** Switching is done client-side by Elementor's frontend script. */
class Tabs extends Widget
{
    public static function name(): string
    {
        return 'tabs';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $id = $this->idInt($element);
        $items = $this->items('tabs', $settings);

        $titles = '';
        $contents = '';

        foreach ($items as $index => $item) {
            $n = $index + 1;
            $first = $n === 1;

            $titles .= $this->el('div', [
                'id' => "{$p}-tab-title-{$id}{$n}",
                'class' => ["{$p}-tab-title", "{$p}-tab-desktop-title"],
                'aria-selected' => $first ? 'true' : 'false',
                'data-tab' => (string) $n,
                'role' => 'tab',
                'tabindex' => $first ? '0' : '-1',
                'aria-controls' => "{$p}-tab-content-{$id}{$n}",
                'aria-expanded' => 'false',
            ], $this->safeHtml((string) ($item['tab_title'] ?? '')));

            $contents .= $this->el('div', [
                'class' => ["{$p}-tab-title", "{$p}-tab-mobile-title"],
                'aria-selected' => $first ? 'true' : 'false',
                'data-tab' => (string) $n,
                'role' => 'tab',
                'tabindex' => $first ? '0' : '-1',
                'aria-controls' => "{$p}-tab-content-{$id}{$n}",
                'aria-expanded' => 'false',
            ], $this->safeHtml((string) ($item['tab_title'] ?? '')));

            $contents .= $this->el('div', [
                'id' => "{$p}-tab-content-{$id}{$n}",
                'class' => ["{$p}-tab-content", "{$p}-clearfix"],
                'data-tab' => (string) $n,
                'role' => 'tabpanel',
                'aria-labelledby' => "{$p}-tab-title-{$id}{$n}",
                'tabindex' => '0',
                'hidden' => $first ? null : true,
            ], $this->richHtml((string) ($item['tab_content'] ?? '')));
        }

        return $this->el('div', ['class' => "{$p}-tabs"],
            $this->el('div', ['class' => "{$p}-tabs-wrapper", 'role' => 'tablist'], $titles)
            . $this->el('div', ['class' => "{$p}-tabs-content-wrapper", 'role' => 'tablist', 'aria-orientation' => 'vertical'], $contents)
        );
    }
}
