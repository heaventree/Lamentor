<?php

namespace Lamentor\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Lamentor\Http\Controllers\Concerns\AuthorizesEditing;
use Lamentor\Models\Document;

class PageController extends Controller
{
    use AuthorizesEditing;

    /** Full-page preview of a document (any status), for editors. */
    public function preview(Document $document): View
    {
        $this->authorizeEditing();

        return view('lamentor::page', ['document' => $document, 'rendered' => $document->render(), 'title' => $document->title]);
    }

    /** The visual editor. */
    public function editor(Document $document): View
    {
        $this->authorizeEditing();

        return view('lamentor::editor', [
            'document' => $document,
            'api' => url(config('lamentor.route_prefix', 'lamentor') . '/api'),
            'previewUrl' => route('lamentor.preview', $document),
            'assetsUrl' => rtrim((string) config('lamentor.assets_url'), '/'),
        ]);
    }
}
