<?php

namespace Lamentor\Library;

/**
 * Somewhere the editor's Library panel can take ready-made blocks from: saved documents, a folder of block files,
 * or a remote service. A block is an Elementor-format element list plus a title; ids are re-keyed on insert.
 */
interface LibrarySource
{
    /** Short, URL-safe name (letters, digits, dashes), unique among registered sources. */
    public function key(): string;

    /** Name shown in the editor. */
    public function label(): string;

    /**
     * Blocks matching an optional search text and category.
     *
     * @return list<array{id: string, title: string, category: string, tags?: list<string>, thumbnail?: ?string, type?: string}>
     */
    public function items(string $search = '', ?string $category = null): array;

    /**
     * One block's content, or null when it does not exist.
     *
     * @return array{title: string, type: string, content: list<array>}|null
     */
    public function get(string $id): ?array;
}
