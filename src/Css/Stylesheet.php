<?php

namespace Lamentor\Css;

/**
 * Rule collector: selector => properties, grouped by media query. Output order
 * is a port of Elementor's Stylesheet (base rules first, then queries from the
 * widest down).
 */
final class Stylesheet
{
    private const BASE = 'all';

    /** @var array<string,array<string,array<string,string>>> */
    private array $rules = [];

    /** @var list<string> font families referenced by `font` controls */
    private array $fonts = [];

    public function addFont(string $family): void
    {
        if (! in_array($family, $this->fonts, true)) {
            $this->fonts[] = $family;
        }
    }

    /** @return list<string> */
    public function fonts(): array
    {
        return $this->fonts;
    }

    /**
     * @param string $declarations e.g. "color:red;margin:0"
     * @param array{min?:int,max?:int} $query pixel bounds
     */
    public function add(string $selector, string $declarations, array $query = []): void
    {
        $key = $this->hash($query);

        if (! isset($this->rules[$key])) {
            $this->rules[$key] = [];
            uksort($this->rules, fn (string $a, string $b) => $this->compare($a, $b));
        }

        foreach (array_filter(explode(';', trim($declarations)), fn ($d) => trim($d) !== '') as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) < 2) {
                continue;
            }
            $this->rules[$key][$selector][trim($parts[0])] = trim($parts[1], ' ;');
        }
    }

    public function isEmpty(): bool
    {
        return $this->__toString() === '';
    }

    public function __toString(): string
    {
        $css = '';
        foreach ($this->rules as $key => $rules) {
            $block = $this->block($rules);
            if ($block === '') {
                continue;
            }
            $css .= $key === self::BASE ? $block : $this->mediaPrefix($key) . '{' . $block . '}';
        }

        return $css;
    }

    private function hash(array $query): string
    {
        $parts = [];
        foreach (['min', 'max'] as $endpoint) {
            if (isset($query[$endpoint])) {
                $parts[] = "{$endpoint}_{$query[$endpoint]}";
            }
        }

        return $parts ? implode('-', $parts) : self::BASE;
    }

    /** @return array{min?:int,max?:int} */
    private function unhash(string $hash): array
    {
        $query = [];
        foreach (explode('-', $hash) as $part) {
            if (preg_match('/^(min|max)_(\d+)$/', $part, $m)) {
                $query[$m[1]] = (int) $m[2];
            }
        }

        return $query;
    }

    private function mediaPrefix(string $hash): string
    {
        $parts = [];
        foreach ($this->unhash($hash) as $endpoint => $value) {
            $parts[] = "({$endpoint}-width:{$value}px)";
        }

        return '@media' . implode(' and ', $parts);
    }

    /** Port of the uksort callback in Elementor's Stylesheet::add_query_hash. */
    private function compare(string $a, string $b): int
    {
        if ($a === self::BASE) {
            return -1;
        }
        if ($b === self::BASE) {
            return 1;
        }

        $qa = $this->unhash($a);
        $qb = $this->unhash($b);

        if (isset($qa['min']) xor isset($qb['min'])) {
            return 1;
        }

        if (isset($qa['min'])) {
            $range = $qa['min'] - $qb['min'];
            if ($range) {
                return $range;
            }

            $aHasMax = isset($qa['max']);
            if ($aHasMax xor isset($qb['max'])) {
                return $aHasMax ? 1 : -1;
            }
            if (! $aHasMax) {
                return 0;
            }
        }

        return ($qb['max'] ?? 0) - ($qa['max'] ?? 0);
    }

    /** @param array<string,array<string,string>> $rules */
    private function block(array $rules): string
    {
        $out = '';
        foreach ($rules as $selector => $props) {
            $body = '';
            foreach ($props as $name => $value) {
                if ($value !== '') {
                    $body .= "{$name}:{$value};";
                }
            }
            if ($body !== '') {
                $out .= "{$selector}{{$body}}";
            }
        }

        return $out;
    }
}
