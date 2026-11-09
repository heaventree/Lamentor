<?php

namespace Lamentor\Dynamic;

/**
 * Dynamic values (original Lamentor design). A saved setting can be bound to a tag:
 *   settings.__dynamic__ = {"title": {"tag": "field", "settings": {"key": "name"}}}
 * The tag's value replaces the setting at render time. For link/media settings the value fills `url`.
 * Context data (e.g. the current loop item or model) is pushed with with().
 */
final class DynamicTags
{
    /** @var array<string,callable(array,array):?string> */
    private array $tags = [];

    /** @var list<array|object> */
    private array $stack = [];

    public function __construct()
    {
        $this->register('text', fn (array $s) => (string) ($s['text'] ?? ''));
        $this->register('site-title', fn () => (string) config('app.name'));
        $this->register('current-date', fn (array $s) => now()->format((string) ($s['format'] ?? 'F j, Y')));
        $this->register('request-url', fn () => url()->current());
        $this->register('user-name', fn () => (string) (auth()->user()?->name ?? ''));
        $this->register('field', fn (array $s, array $ctx) => ($v = data_get($ctx, (string) ($s['key'] ?? ''))) === null || is_array($v) || is_object($v) ? ($s['fallback'] ?? null) : (string) $v);
    }

    /** @param callable(array $settings, array $context):?string $resolver */
    public function register(string $name, callable $resolver): void
    {
        $this->tags[$name] = $resolver;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tags);
    }

    /** Run $fn with $data as the context for `field` tags. */
    public function with(array|object $data, callable $fn): mixed
    {
        $this->stack[] = $data;
        try {
            return $fn();
        } finally {
            array_pop($this->stack);
        }
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        $top = end($this->stack);

        return $top === false ? [] : (is_array($top) ? $top : (method_exists($top, 'toArray') ? $top->toArray() : get_object_vars($top)));
    }

    public function resolve(string $tag, array $settings = []): ?string
    {
        $resolver = $this->tags[$tag] ?? null;

        return $resolver ? $resolver($settings, $this->context()) : null;
    }

    /**
     * @param array<string,mixed> $settings merged settings
     * @param array<string,array{tag?:string,settings?:array}> $dynamic
     * @return array<string,mixed>
     */
    public function apply(array $settings, array $dynamic): array
    {
        foreach ($dynamic as $key => $binding) {
            if (! is_array($binding) || ! isset($binding['tag']) || ! preg_match('/^[A-Za-z0-9_\-]+$/', (string) $key)) {
                continue;
            }

            $value = $this->resolve((string) $binding['tag'], (array) ($binding['settings'] ?? []));
            if ($value === null) {
                continue;
            }

            $settings[$key] = is_array($settings[$key] ?? null) ? ['url' => $value] + $settings[$key] : $value;
        }

        return $settings;
    }
}
