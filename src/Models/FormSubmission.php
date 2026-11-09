<?php

namespace Lamentor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $table = 'lamentor_form_submissions';

    protected $fillable = ['document_id', 'element_id', 'form_name', 'data', 'ip'];

    protected $casts = ['data' => 'array'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
