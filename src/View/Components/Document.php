<?php

namespace Lamentor\View\Components;

use Illuminate\View\Component;
use Illuminate\Contracts\View\View;
use Lamentor\Models\Document as DocumentModel;
use Lamentor\Rendering\RenderedDocument;
use Lamentor\Rendering\Renderer;

/**
 * <x-lamentor::document :document="$page" /> prints the stylesheet links, the
 * document's CSS and its HTML. Use `:head="false"` to print only the markup and
 * place <x-lamentor::head :document="$page" /> in your layout's <head> instead.
 */
class Document extends Component
{
    public RenderedDocument $rendered;

    public string $assets;

    /** @param DocumentModel|RenderedDocument|array|string $document model, rendered result, element array or JSON */
    public function __construct(DocumentModel|RenderedDocument|array|string $document, public bool $head = true)
    {
        $this->rendered = match (true) {
            $document instanceof RenderedDocument => $document,
            $document instanceof DocumentModel => $document->render(),
            default => app(Renderer::class)->document($document, 0),
        };
        $this->assets = rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/assets';
    }

    public function render(): View
    {
        return view('lamentor::components.document');
    }
}
