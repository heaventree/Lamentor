<?php

namespace Lamentor\Library;

use Lamentor\Models\Document;

/** Blocks saved in this app: documents whose type is one of the configured block types (section, container). */
final class LocalSource implements LibrarySource
{
    /** @param list<string> $types */
    public function __construct(private readonly array $types = ['section', 'container'])
    {
    }

    public function key(): string
    {
        return 'saved';
    }

    public function label(): string
    {
        return 'Saved blocks';
    }

    public function items(string $search = '', ?string $category = null): array
    {
        return Document::query()
            ->whereIn('type', $this->types)
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get(['id', 'title', 'type', 'settings'])
            ->map(fn (Document $d) => [
                'id' => (string) $d->id,
                'title' => $d->title ?: 'Untitled block',
                'category' => (string) (($d->settings ?? [])['lamentor_library']['category'] ?? 'Saved'),
                'tags' => (array) (($d->settings ?? [])['lamentor_library']['tags'] ?? []),
                'type' => $d->type,
            ])
            ->filter(fn (array $item) => Library::matches($item, $search, $category))
            ->values()
            ->all();
    }

    public function get(string $id): ?array
    {
        if (! ctype_digit($id)) {
            return null;
        }
        $document = Document::query()->whereIn('type', $this->types)->find((int) $id);

        return $document ? ['title' => (string) $document->title, 'type' => (string) $document->type, 'content' => $document->data ?? []] : null;
    }
}
