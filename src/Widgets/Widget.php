<?php

namespace Lamentor\Widgets;

use Lamentor\Dynamic\DynamicTags;
use Lamentor\Elements\Element;
use Lamentor\Rendering\Attributes;
use Lamentor\Rendering\Renderer;
use Lamentor\Schema\SchemaRepository;
use Lamentor\Support\Conditions;
use Lamentor\Support\Html;

/**
 * Base class for renderable elements. Controls, defaults and style selectors
 * come from the schema generated out of Elementor's own widget classes, so a
 * widget subclass only needs a name and a render() method.
 */
abstract class Widget
{
    abstract public static function name(): string;

    /** Render the inner content of the widget. $settings are the active settings. */
    abstract protected function render(array $settings, Element $element, Renderer $renderer): string;

    /** Schema controls for this element (widgets add the shared "common" controls). @return array<string,array<string,mixed>> */
    public function controls(): array
    {
        return app(SchemaRepository::class)->widgetControls(static::name());
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        return app(SchemaRepository::class)->defaultsFor(static::class, $this->controls());
    }

    protected function prefix(): string
    {
        return rtrim((string) config('lamentor.class_prefix', 'elementor'), '-');
    }

    /**
     * Saved settings merged over the control defaults. Array-valued controls
     * (slider, dimensions...) are merged key by key, like Elementor does.
     *
     * @return array<string,mixed>
     */
    final public function settingsFor(Element $element): array
    {
        $saved = $element->settings;
        $settings = [];

        foreach ($this->defaults() as $name => $default) {
            if (! array_key_exists($name, $saved)) {
                $settings[$name] = $default;

                continue;
            }

            $value = $saved[$name];
            $settings[$name] = is_array($default) && is_array($value) && $default && ! array_is_list($default)
                ? array_merge($default, $value)
                : $value;
        }

        $settings += $saved;

        return ! empty($saved['__dynamic__']) && is_array($saved['__dynamic__'])
            ? app(DynamicTags::class)->apply($settings, $saved['__dynamic__'])
            : $settings;
    }

    /**
     * Settings whose control is currently visible; hidden controls become null
     * (Elementor's get_settings_for_display).
     *
     * @return array<string,mixed>
     */
    final public function activeSettings(array $settings): array
    {
        $controls = $this->controls();
        $active = [];

        foreach ($settings as $name => $value) {
            $active[$name] = ! isset($controls[$name]) || Conditions::visible($controls[$name], $settings, $controls)
                ? $value
                : null;
        }

        return $active;
    }

    /** Wrapper attributes shared by every element type. */
    protected function wrapperAttributes(Element $element, array $settings, array $extraClasses = []): Attributes
    {
        $p = $this->prefix();
        $attrs = new Attributes();

        $attrs->add('_wrapper', 'class', ["{$p}-element", "{$p}-element-{$element->id}", ...$extraClasses]);
        $attrs->add('_wrapper', 'data-id', $element->id);
        $attrs->add('_wrapper', 'data-element_type', $element->type);
        $attrs->add('_wrapper', 'data-e-type', $element->type);

        $controls = $this->controls();
        foreach ($settings as $name => $value) {
            if (! isset($controls[$name]) || ! array_key_exists('prefix_class', $controls[$name]) || $controls[$name]['prefix_class'] === null) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $value = $controls[$name]['classes_dictionary'][$value] ?? $value;
            if (empty($value) && $value !== '0') {
                continue;
            }
            $attrs->add('_wrapper', 'class', preg_replace('/^elementor/', $p, (string) $controls[$name]['prefix_class']) . $value);
        }

        $animated = ! empty($settings['_animation']) && $settings['_animation'] !== 'none'
            || ! empty($settings['animation']) && $settings['animation'] !== 'none';
        if ($animated && config('lamentor.animations', true)) {
            // Hidden until the frontend script plays the entrance animation.
            $attrs->add('_wrapper', 'class', "{$p}-invisible");
        }

        if (! empty($settings['_element_id'])) {
            $attrs->add('_wrapper', 'id', trim((string) $settings['_element_id']));
        }

        $frontend = $this->frontendSettings($settings);
        if ($frontend) {
            $attrs->add('_wrapper', 'data-settings', json_encode($frontend, JSON_UNESCAPED_SLASHES));
        }

        return $attrs;
    }

    /** Settings the frontend JavaScript reads (`frontend_available` controls). @return array<string,mixed> */
    protected function frontendSettings(array $settings): array
    {
        $out = [];
        foreach ($this->controls() as $name => $control) {
            if (empty($control['frontend_available'])) {
                continue;
            }
            $value = $settings[$name] ?? null;
            if ($value !== null && $value !== '') {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    public function renderElement(Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $settings = $this->activeSettings($this->settingsFor($element));

        $attrs = $this->wrapperAttributes($element, $settings, [
            "{$p}-widget",
            "{$p}-widget-" . static::name(),
        ]);
        $attrs->add('_wrapper', 'data-widget_type', static::name() . '.' . ($settings['_skin'] ?? 'default'));

        $inner = $this->render($settings, $element, $renderer);

        return '<div ' . $attrs->string('_wrapper') . '>'
            . "<div class=\"{$p}-widget-container\">{$inner}</div>"
            . '</div>';
    }

    /** A saved HTML tag name, limited to safe text containers. */
    protected function tag(mixed $tag, string $fallback = 'div'): string
    {
        return in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p'], true) ? $tag : $fallback;
    }

    /** Inline-level allowlist sanitizer (headings, buttons). */
    protected function safeHtml(string $html): string
    {
        return Html::sanitize($html, Html::INLINE);
    }

    /** Block-level allowlist sanitizer (rich text). */
    protected function richHtml(string $html): string
    {
        return Html::sanitize($html, Html::RICH);
    }

    /**
     * Attribute string from name => value|list. null/false drop the attribute,
     * true makes it a bare attribute, '' keeps it empty (e.g. alt="").
     */
    protected function attrs(array $map): string
    {
        $out = [];
        foreach ($map as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $out[] = $name;

                continue;
            }
            $value = is_array($value) ? implode(' ', array_filter($value, fn ($v) => $v !== null && $v !== '' && $v !== false)) : (string) $value;
            $out[] = $name . '="' . e($value) . '"';
        }

        return implode(' ', $out);
    }

    /** One HTML element. $inner is trusted (already escaped/sanitized) markup. */
    protected function el(string $tag, array $attrs, string $inner = ''): string
    {
        $attributes = $this->attrs($attrs);

        return '<' . $tag . ($attributes !== '' ? ' ' . $attributes : '') . '>' . $inner . '</' . $tag . '>';
    }

    /**
     * <img> for an Elementor media value {url, id, alt}. Sizes are only
     * reflected in the class names (there is no media library to resize from).
     */
    protected function imageHtml(array $image, string $size = 'full', array $classes = [], array $extra = []): string
    {
        $url = (string) ($image['url'] ?? '');
        if ($url === '' || preg_match('/^(javascript|vbscript):/i', preg_replace('/[\x00-\x20]+/', '', $url))) {
            return '';
        }

        $size = preg_replace('/[^A-Za-z0-9_-]/', '', $size) ?: 'full';
        $all = ["attachment-{$size}", "size-{$size}", ...$classes];
        if (! empty($image['id'])) {
            $all[] = 'wp-image-' . (int) $image['id'];
        }

        return '<img ' . $this->attrs(['src' => $url, 'alt' => (string) ($image['alt'] ?? ''), 'class' => $all] + $extra) . '>';
    }

    /** Numeric id Elementor uses to build unique DOM ids (first three digits of the hex id as an int). */
    protected function idInt(Element $element): string
    {
        return substr((string) hexdec($element->id), 0, 3);
    }

    /**
     * Repeater items with each field's default filled in.
     *
     * @return list<array<string,mixed>>
     */
    protected function items(string $key, array $settings): array
    {
        $fields = $this->controls()[$key]['fields'] ?? [];
        $defaults = [];
        foreach ($fields as $name => $field) {
            if (array_key_exists('default', $field)) {
                $defaults[$name] = $field['default'];
            }
        }

        $items = [];
        foreach ((array) ($settings[$key] ?? []) as $item) {
            if (is_array($item)) {
                $items[] = array_replace($defaults, $item);
            }
        }

        return $items;
    }

    /** Elementor link control: {url, is_external, nofollow, custom_attributes}. */
    protected function addLink(Attributes $attrs, string $key, array $link): void
    {
        $url = (string) ($link['url'] ?? '');
        if ($url === '' || preg_match('/^(javascript|vbscript|data):/i', preg_replace('/[\x00-\x20]+/', '', $url))) {
            return;
        }

        $attrs->add($key, 'href', $url);

        $rel = [];
        if (! empty($link['is_external'])) {
            $attrs->add($key, 'target', '_blank');
            $rel[] = 'noopener';
        }
        if (! empty($link['nofollow'])) {
            $rel[] = 'nofollow';
        }
        if ($rel) {
            $attrs->add($key, 'rel', $rel);
        }
    }
}
