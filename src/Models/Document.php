<?php

namespace Lamentor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lamentor\Rendering\RenderedDocument;
use Lamentor\Rendering\Renderer;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string $type
 * @property string $status
 * @property array<int,array<string,mixed>>|null $data Elementor element tree
 * @property array<string,mixed>|null $settings
 */
class Document extends Model
{
    protected $table = 'lamentor_documents';

    protected $fillable = ['title', 'slug', 'type', 'status', 'data', 'settings', 'author_id'];

    protected $casts = ['data' => 'array', 'settings' => 'array'];

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'document_id');
    }

    public function render(): RenderedDocument
    {
        return app(Renderer::class)->document($this->data ?? [], $this->getKey() ?? 0, $this->elementorType());
    }

    /** The data-elementor-type Elementor uses for this kind of document. */
    public function elementorType(): string
    {
        return match ($this->type) {
            'section', 'container' => 'section',
            'header', 'footer' => $this->type,
            default => 'wp-page',
        };
    }

    /**
     * Store new content, keeping the previous one as a revision (the newest
     * `lamentor.max_revisions` are kept).
     *
     * @param array<int,array<string,mixed>> $data
     */
    public function saveContent(array $data, ?array $settings = null, ?int $authorId = null): void
    {
        if ($this->exists && ($this->data !== null || $this->settings !== null)) {
            $this->revisions()->create(['data' => $this->data, 'settings' => $this->settings, 'author_id' => $authorId]);

            $keep = (int) config('lamentor.max_revisions', 20);
            $stale = $this->revisions()->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->pluck('id');
            if ($stale->isNotEmpty()) {
                $this->revisions()->whereIn('id', $stale)->delete();
            }
        }

        $this->data = $data;
        if ($settings !== null) {
            $this->settings = $settings;
        }
        $this->save();
    }
}
