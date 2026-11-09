<?php

namespace Lamentor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revision extends Model
{
    protected $table = 'lamentor_document_revisions';

    protected $fillable = ['document_id', 'data', 'settings', 'author_id'];

    protected $casts = ['data' => 'array', 'settings' => 'array'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
