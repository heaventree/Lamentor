<?php

namespace Lamentor\Rendering;

use Lamentor\Css\Breakpoints;
use Lamentor\Css\StyleGenerator;
use Lamentor\Css\Stylesheet;
use Lamentor\Elements\Element;
use Lamentor\Kit\Kit;
use Lamentor\Support\Assets;
use Lamentor\Support\Fonts;
use Lamentor\Widgets\Widget;
use Lamentor\Widgets\WidgetRegistry;

final class Renderer
{
    public function __construct(
        private readonly WidgetRegistry $registry,
        private readonly StyleGenerator $styles,
        private readonly ?Kit $kit = null,
        private readonly ?Breakpoints $breakpoints = null,
    ) {
    }

    /** Render a document given as Elementor `_elementor_data` JSON. */
    public function renderJson(string $json): string
    {
        return $this->renderAll(Element::documentFromJson($json));
    }

    /**
     * Everything needed to show a document: markup, CSS (kit variables + the
     * document's own rules), the static stylesheets to link and the web fonts.
     */
    private int|string $documentId = 0;

    /** Id of the document being rendered (0 for unsaved previews). */
    public function documentId(): int|string
    {
        return $this->documentId;
    }

    public function document(string|array $json, int|string $documentId, string $type = 'wp-page'): RenderedDocument
    {
        $this->documentId = $documentId;
        $elements = is_array($json)
            ? array_map(fn (array $node) => Element::fromArray($node), $json)
            : Element::documentFromJson($json);
        $p = rtrim((string) config('lamentor.class_prefix', 'elementor'), '-');

        $sheet = new Stylesheet();
        $this->collectCss($elements, $sheet, $documentId);

        $css = ($this->kit && $this->breakpoints ? $this->kit->css($this->breakpoints, $p) : '') . $sheet;

        $families = $sheet->fonts();
        if ($this->kit) {
            foreach (array_merge((array) $this->kit->settings()['system_typography'], (array) $this->kit->settings()['custom_typography']) as $global) {
                $families[] = (string) ($global['typography_font_family'] ?? '');
            }
        }
        $fonts = Fonts::webFonts($families, (array) config('lamentor.system_fonts', []));

        $html = '<div data-elementor-type="' . e($type) . '" data-elementor-id="' . e((string) $documentId) . '" class="' . "{$p} {$p}-{$documentId}" . '">'
            . $this->renderAll($elements) . '</div>';

        return new RenderedDocument($html, $css, Assets::stylesheetsFor($elements), $fonts, Fonts::googleUrl($fonts, (string) config('lamentor.font_display', 'auto')));
    }

    /**
     * Generate the CSS for a document. $documentId is the page id used in the
     * `.elementor-{id}` scope class that wraps the rendered page.
     */
    public function cssForJson(string $json, int|string $documentId): string
    {
        $sheet = new Stylesheet();
        $this->collectCss(Element::documentFromJson($json), $sheet, $documentId);

        return (string) $sheet;
    }

    /**
     * Static stylesheet files (from `Assets::directory()`) a document needs.
     *
     * @return list<string>
     */
    public function stylesheetsForJson(string $json): array
    {
        return Assets::stylesheetsFor(Element::documentFromJson($json));
    }

    /** @param list<Element> $elements */
    public function renderAll(array $elements): string
    {
        return implode('', array_map(fn (Element $e) => $this->render($e), $elements));
    }

    public function renderChildren(Element $element): string
    {
        return $this->renderAll($element->children);
    }

    public function render(Element $element): string
    {
        return $this->handler($element)?->renderElement($element, $this) ?? '';
    }

    /** @param list<Element> $elements */
    private function collectCss(array $elements, Stylesheet $sheet, int|string $documentId): void
    {
        $p = rtrim((string) config('lamentor.class_prefix', 'elementor'), '-');

        foreach ($elements as $element) {
            $handler = $this->handler($element);

            if ($handler) {
                $this->styles->generate(
                    $sheet,
                    $handler->controls(),
                    $handler->settingsFor($element),
                    [
                        '{{WRAPPER}}' => ".{$p}-{$documentId} .{$p}-element.{$p}-element-{$element->id}",
                        '{{ID}}' => $element->id,
                    ],
                );
            }

            $this->collectCss($element->children, $sheet, $documentId);
        }
    }

    /** Null for unknown or not-yet-ported elements: they render nothing rather than fail the page. */
    private function handler(Element $element): ?Widget
    {
        if ($element->type === 'widget') {
            return $element->widgetType !== null && $this->registry->has($element->widgetType)
                ? $this->registry->widget($element->widgetType)
                : null;
        }

        return $this->registry->element($element->type);
    }
}
