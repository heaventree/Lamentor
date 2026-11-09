<?php

namespace Lamentor\Library;

use Lamentor\Templates\TemplateFormat;

/**
 * Blocks shipped as files, one folder per block:
 *
 *   <root>/<category>/<slug>/block.json   Elementor template export or bare element list (required)
 *   <root>/<category>/<slug>/meta.json    {"title": "...", "tags": [...], "type": "section"} (optional)
 *   <root>/<category>/<slug>/thumb.{webp,png,jpg}                                         (optional)
 *
 * The block id is "<category>.<slug>". Category and slug folders must be lowercase letters, digits and dashes,
 * so an id can never point outside the root.
 */
final class DirectorySource implements LibrarySource, ProvidesThumbnails
{
    private const NAME = '[a-z0-9][a-z0-9-]*';

    /** @var array<string, array{dir: string, item: array}>|null */
    private ?array $index = null;

    public function __construct(
        private readonly string $root,
        private readonly string $key = 'blocks',
        private readonly string $label = 'Block library',
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function items(string $search = '', ?string $category = null): array
    {
        return array_values(array_filter(
            array_map(fn (array $entry) => $entry['item'], $this->index()),
            fn (array $item) => Library::matches($item, $search, $category),
        ));
    }

    public function get(string $id): ?array
    {
        $entry = $this->index()[$id] ?? null;
        if (! $entry) {
            return null;
        }
        $raw = @file_get_contents($entry['dir'] . '/block.json');
        if ($raw === false) {
            return null;
        }
        $parsed = TemplateFormat::parse($raw);

        return ['title' => $entry['item']['title'], 'type' => $entry['item']['type'], 'content' => $parsed['content']];
    }

    public function thumbnailPath(string $id): ?string
    {
        $entry = $this->index()[$id] ?? null;

        return $entry ? $this->findThumb($entry['dir']) : null;
    }

    /** @return array<string, array{dir: string, item: array}> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }
        $this->index = [];
        if (! is_dir($this->root)) {
            return $this->index;
        }

        foreach (glob(rtrim($this->root, '/') . '/*/*/block.json') ?: [] as $file) {
            $dir = dirname($file);
            $slug = basename($dir);
            $category = basename(dirname($dir));
            if (! preg_match('/^' . self::NAME . '$/', $slug) || ! preg_match('/^' . self::NAME . '$/', $category)) {
                continue;
            }
            $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true);
            $meta = is_array($meta) ? $meta : [];
            $id = $category . '.' . $slug;

            $this->index[$id] = ['dir' => $dir, 'item' => [
                'id' => $id,
                'title' => (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $slug))),
                'category' => (string) ($meta['category'] ?? ucwords(str_replace('-', ' ', $category))),
                'tags' => array_values(array_map('strval', (array) ($meta['tags'] ?? []))),
                'type' => (string) ($meta['type'] ?? 'section'),
            ]];
        }
        ksort($this->index);

        return $this->index;
    }

    private function findThumb(string $dir): ?string
    {
        foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
            if (is_file("$dir/thumb.$ext")) {
                return "$dir/thumb.$ext";
            }
        }

        return null;
    }
}
