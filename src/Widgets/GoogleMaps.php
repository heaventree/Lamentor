<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;

class GoogleMaps extends Widget
{
    public static function name(): string
    {
        return 'google_maps';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $address = (string) ($settings['address'] ?? '');
        if ($address === '') {
            return '';
        }

        $zoom = abs((int) ($settings['zoom']['size'] ?? 10)) ?: 10;
        $key = (string) config('lamentor.google_maps_api_key', '');

        $url = $key !== ''
            ? sprintf('https://www.google.com/maps/embed/v1/place?key=%s&q=%s&zoom=%d', rawurlencode($key), rawurlencode($address), $zoom)
            : sprintf('https://maps.google.com/maps?q=%s&t=m&z=%d&output=embed&iwloc=near', rawurlencode($address), $zoom);

        return $this->el('div', ['class' => $this->prefix() . '-custom-embed'],
            $this->el('iframe', ['loading' => 'lazy', 'src' => $url, 'title' => $address, 'aria-label' => $address]));
    }
}
