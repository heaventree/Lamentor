<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Schema\SchemaRepository;

/**
 * Flexbox / grid container (Elementor "e-con"). Layout is done by CSS custom
 * properties on the element plus Elementor's base stylesheet (see Support\Assets),
 * so most controls just set variables such as --flex-direction or --gap.
 *
 * Not ported yet: video/slideshow backgrounds, shape dividers, motion effects.
 */
class Container extends Widget
{
    private const TAGS = ['div', 'header', 'footer', 'main', 'article', 'section', 'aside', 'nav', 'a'];

    public static function name(): string
    {
        return 'container';
    }

    public function controls(): array
    {
        return app(SchemaRepository::class)->elementControls('container');
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        return $renderer->renderChildren($element);
    }

    public function renderElement(Element $element, Renderer $renderer): string
    {
        $settings = $this->activeSettings($this->settingsFor($element));

        $tag = in_array($settings['html_tag'] ?? 'div', self::TAGS, true) ? $settings['html_tag'] : 'div';

        $attrs = $this->wrapperAttributes($element, $settings, ['e-con', $element->isInner ? 'e-child' : 'e-parent']);

        if (! empty($settings['link']['url'])) {
            $this->addLink($attrs, '_wrapper', $settings['link']);
        }

        // An <a> container without a usable link would be invalid markup.
        if ($tag === 'a' && ! str_contains($attrs->string('_wrapper'), 'href=')) {
            $tag = 'div';
        }

        $children = $this->render($settings, $element, $renderer);

        if (($settings['content_width'] ?? 'boxed') === 'boxed') {
            $children = '<div class="e-con-inner">' . $children . '</div>';
        }

        return "<{$tag} " . $attrs->string('_wrapper') . ">{$children}</{$tag}>";
    }
}
