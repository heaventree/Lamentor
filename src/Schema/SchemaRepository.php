<?php

namespace Lamentor\Schema;

/**
 * Loads the control schemas generated from Elementor's own widget classes
 * (see tools/dump-schema). A schema is {name, title?, icon?, categories?, controls: {name: control}}.
 */
final class SchemaRepository
{
    /** @var array<string,array|null> */
    private array $cache = [];

    /** @var array<string,array<string,mixed>> per-schema default settings */
    private array $defaults = [];

    /** @var array<string,array<string,array<string,mixed>>> widget controls merged with the common ones */
    private array $merged = [];

    public function __construct(private readonly ?string $directory = null)
    {
    }

    private function directory(): string
    {
        return $this->directory ?? __DIR__ . '/../../resources/schema';
    }

    /** @return array<string,mixed>|null */
    public function widget(string $name): ?array
    {
        return $this->load("widgets/{$name}");
    }

    /** container, section, column or common (the controls shared by all widgets). */
    public function element(string $name): ?array
    {
        return $this->load("elements/{$name}");
    }

    /** @return list<string> */
    public function widgetNames(): array
    {
        return array_map(fn (string $f) => basename($f, '.json'), glob($this->directory() . '/widgets/*.json') ?: []);
    }

    /**
     * Controls for a widget, including the shared "common" ones (advanced tab).
     *
     * @return array<string,array<string,mixed>>
     */
    public function widgetControls(string $name): array
    {
        return $this->merged[$name] ??= ($this->widget($name)['controls'] ?? []) + ($this->element('common')['controls'] ?? []) + $this->extras();
    }

    /** @return array<string,array<string,mixed>> */
    public function elementControls(string $name): array
    {
        return ($this->element($name)['controls'] ?? []) + $this->extras();
    }

    /** Lamentor's own advanced controls (sticky, scroll reveal), available on every element. */
    private function extras(): array
    {
        return $this->element('lamentor-extras')['controls'] ?? [];
    }

    /**
     * Default value of every data control (those that declare one).
     *
     * @param array<string,array<string,mixed>> $controls
     * @return array<string,mixed>
     */
    public function defaultsFor(string $cacheKey, array $controls): array
    {
        return $this->defaults[$cacheKey] ??= $this->collectDefaults($controls);
    }

    /** @return array<string,mixed> */
    public function collectDefaults(array $controls): array
    {
        $defaults = [];
        foreach ($controls as $name => $control) {
            if (array_key_exists('default', $control)) {
                $defaults[$name] = $control['default'];
            }
        }

        return $defaults;
    }

    private function load(string $path): ?array
    {
        if (array_key_exists($path, $this->cache)) {
            return $this->cache[$path];
        }

        $file = $this->directory() . "/{$path}.json";
        $raw = is_file($file) ? (string) file_get_contents($file) : '';
        // Defaults such as the placeholder image carry a marker for Elementor's asset URL.
        $raw = str_replace('@@ELEMENTOR_URL@@/', rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/', $raw);
        $data = $raw !== '' ? json_decode($raw, true) : null;

        return $this->cache[$path] = is_array($data) ? $data : null;
    }
}
