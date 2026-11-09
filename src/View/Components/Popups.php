<?php

namespace Lamentor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Lamentor\Templates\TemplateMatcher;

/**
 * <x-lamentor::popups /> prints every published popup whose display conditions match this request.
 * Popup settings: trigger (load|delay|click|exit), delay (seconds), selector (CSS, for "click"), once (show once per visitor).
 */
class Popups extends Component
{
    /** @var list<array<string,mixed>> */
    public array $popups = [];

    public string $assets;

    public function __construct()
    {
        foreach (app(TemplateMatcher::class)->allForLocation('popup') as $doc) {
            $s = (array) $doc->settings;
            $trigger = in_array($s['trigger'] ?? 'load', ['load', 'delay', 'click', 'exit'], true) ? $s['trigger'] : 'load';
            $this->popups[] = [
                'id' => $doc->id,
                'title' => $doc->title ?: 'Popup',
                'rendered' => $doc->render(),
                'trigger' => $trigger,
                'delay' => max(0, (int) ($s['delay'] ?? 3)),
                'selector' => (string) ($s['selector'] ?? ''),
                'once' => (bool) ($s['once'] ?? true),
                'version' => $doc->updated_at?->timestamp ?? 0,
            ];
        }
        $this->assets = rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/assets';
    }

    public function render(): View
    {
        return view('lamentor::components.popups');
    }
}
