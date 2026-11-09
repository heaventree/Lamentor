<?php

namespace Lamentor\Support;

/** Builds the Google Fonts stylesheet URL for the font families a document uses. */
final class Fonts
{
    /** Families the browser already has: no web font needed. */
    private const SYSTEM = [
        'arial', 'tahoma', 'verdana', 'helvetica', 'times new roman', 'trebuchet ms', 'georgia',
        'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy', 'system-ui',
    ];

    /**
     * @param list<string> $families
     * @return list<string> families that need a web font
     */
    public static function webFonts(array $families, array $extraSystem = []): array
    {
        $skip = array_merge(self::SYSTEM, array_map('strtolower', $extraSystem));
        $out = [];

        foreach ($families as $family) {
            $family = trim((string) $family);
            if ($family !== '' && ! in_array(strtolower($family), $skip, true) && ! in_array($family, $out, true)) {
                $out[] = $family;
            }
        }

        return $out;
    }

    /** @param list<string> $families web-font families (see webFonts()) */
    public static function googleUrl(array $families, string $display = 'auto'): ?string
    {
        if (! $families) {
            return null;
        }

        $weights = '100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic';
        $parts = array_map(fn (string $f) => str_replace(' ', '+', $f) . ':' . $weights, $families);

        return 'https://fonts.googleapis.com/css?family=' . implode('%7C', array_map(fn ($p) => str_replace([':', ','], ['%3A', '%2C'], $p), $parts)) . '&display=' . $display;
    }
}
