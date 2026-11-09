<?php

namespace Lamentor\Rendering;

/** Everything a page needs to show one Elementor-format document. */
final class RenderedDocument
{
    /**
     * @param list<string> $stylesheets static files (see Support\Assets) to link, base first
     * @param list<string> $fonts web-font families used
     */
    public function __construct(
        public readonly string $html,
        public readonly string $css,
        public readonly array $stylesheets,
        public readonly array $fonts,
        public readonly ?string $fontsUrl,
    ) {
    }
}
