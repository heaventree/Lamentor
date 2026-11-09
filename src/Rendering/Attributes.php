<?php

namespace Lamentor\Rendering;

/**
 * Collects HTML attributes per named key, like Elementor's add_render_attribute().
 */
final class Attributes
{
    /** @var array<string,array<string,list<string>>> */
    private array $bag = [];

    /** @param string|list<string> $value */
    public function add(string $key, string $name, string|array $value): self
    {
        foreach ((array) $value as $v) {
            $this->bag[$key][$name][] = (string) $v;
        }

        return $this;
    }

    public function string(string $key): string
    {
        $out = [];

        foreach ($this->bag[$key] ?? [] as $name => $values) {
            $joined = e(implode(' ', array_unique($values)));
            $out[] = $name . '="' . $joined . '"';
        }

        return implode(' ', $out);
    }
}
