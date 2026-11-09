<?php

namespace Lamentor\Dynamic;

use Lamentor\Models\Document;

/**
 * Named data sources for the Loop Grid widget. Register your own in a service provider:
 *   app(LoopSources::class)->register('posts', fn (int $limit) => Post::latest()->limit($limit)->get());
 * Each item (array or object/model) becomes the context for `field` dynamic tags inside the loop template.
 */
final class LoopSources
{
    /** @var array<string,callable(int):iterable> */
    private array $sources = [];

    public function __construct()
    {
        $this->register('documents', fn (int $limit) => Document::query()->where('type', 'page')->where('status', 'publish')
            ->orderByDesc('updated_at')->limit($limit)->get(['id', 'title', 'slug', 'updated_at'])->map->toArray()->all());
    }

    /** @param callable(int):iterable $source */
    public function register(string $name, callable $source): void
    {
        $this->sources[$name] = $source;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->sources);
    }

    /** @return iterable<array|object> */
    public function items(string $name, int $limit): iterable
    {
        $limit = max(1, min($limit, 100));

        return isset($this->sources[$name]) ? ($this->sources[$name])($limit) : [];
    }
}
