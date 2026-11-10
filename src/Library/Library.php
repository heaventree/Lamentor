<?php

namespace Lamentor\Library;

use InvalidArgumentException;

/** The registered library sources, in the order the editor lists them. */
final class Library
{
    /** @var array<string, LibrarySource> */
    private array $sources = [];

    public function register(LibrarySource $source): self
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $source->key())) {
            throw new InvalidArgumentException("Library source key [{$source->key()}] must be lowercase letters, digits and dashes.");
        }
        $this->sources[$source->key()] = $source;

        return $this;
    }

    public function get(string $key): ?LibrarySource
    {
        return $this->sources[$key] ?? null;
    }

    /** @return array<string, LibrarySource> */
    public function all(): array
    {
        return $this->sources;
    }

    /** Keeps the fields the editor uses and drops anything else a source returned. */
    public static function normalizeItem(array $item, string $source): ?array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return null;
        }

        return [
            'source' => $source,
            'id' => $id,
            'title' => (string) ($item['title'] ?? $id),
            'category' => (string) ($item['category'] ?? ''),
            'tags' => array_values(array_map('strval', (array) ($item['tags'] ?? []))),
            'thumbnail' => isset($item['thumbnail']) && is_string($item['thumbnail']) ? $item['thumbnail'] : null,
            'type' => (string) ($item['type'] ?? 'section'),
        ];
    }

    /** Case-insensitive match on title and tags. */
    public static function matches(array $item, string $search, ?string $category): bool
    {
        if ($category !== null && $category !== '' && strcasecmp((string) ($item['category'] ?? ''), $category) !== 0) {
            return false;
        }
        $search = trim($search);
        if ($search === '') {
            return true;
        }
        $haystack = mb_strtolower(($item['title'] ?? '') . ' ' . implode(' ', (array) ($item['tags'] ?? [])));

        return str_contains($haystack, mb_strtolower($search));
    }
}
