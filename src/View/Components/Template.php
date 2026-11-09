<?php

namespace Lamentor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Lamentor\Models\Document;
use Lamentor\Rendering\RenderedDocument;
use Lamentor\Templates\TemplateMatcher;

/** <x-lamentor::template location="header" /> prints the matching theme template, or nothing. */
class Template extends Component
{
    public ?RenderedDocument $rendered = null;

    public string $assets;

    public function __construct(public string $location, public bool $head = true, ?Document $document = null)
    {
        $document ??= app(TemplateMatcher::class)->forLocation($location);
        $this->rendered = $document?->render();
        $this->assets = rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/assets';
    }

    public function render(): View
    {
        return view('lamentor::components.template');
    }
}
