<?php

namespace Lamentor\Templates;

use Illuminate\Http\Request;
use Lamentor\Models\Document;

/**
 * Theme-builder style templates (original Lamentor design). A published document of type
 * header / footer / single / archive / 404 / section is shown where its display conditions match.
 *
 * Conditions live in `settings.conditions`, a list of rules:
 *   {"type": "all"}                      every page
 *   {"type": "path",  "value": "blog/*"} request path pattern (Request::is)
 *   {"type": "route", "value": "posts.*"} route name pattern (Request::routeIs)
 *   {"type": "url",   "value": "..."}    exact full URL
 * add "exclude": true to subtract. The most specific matching template wins (path/route/url beat all),
 * then the most recently updated.
 */
final class TemplateMatcher
{
    public const LOCATIONS = ['header', 'footer', 'single', 'archive', '404', 'section', 'popup'];

    public function forLocation(string $location, ?Request $request = null): ?Document
    {
        if (! in_array($location, self::LOCATIONS, true)) {
            return null;
        }

        $request ??= request();
        $best = null;
        $bestScore = 0;

        $candidates = Document::query()->where('type', $location)->where('status', 'publish')->orderByDesc('updated_at')->get();

        foreach ($candidates as $document) {
            $score = $this->score((array) ($document->settings['conditions'] ?? []), $request);
            // Strictly greater: with equal scores the more recently updated (first) one stays.
            if ($score > $bestScore) {
                [$best, $bestScore] = [$document, $score];
            }
        }

        return $best;
    }

    /** Every published template of this type whose conditions match, most specific first. @return list<Document> */
    public function allForLocation(string $location, ?Request $request = null): array
    {
        if (! in_array($location, self::LOCATIONS, true)) {
            return [];
        }
        $request ??= request();
        $scored = [];
        foreach (Document::query()->where('type', $location)->where('status', 'publish')->orderByDesc('updated_at')->get() as $doc) {
            if (($score = $this->score((array) ($doc->settings['conditions'] ?? []), $request)) > 0) {
                $scored[] = [$score, $doc];
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn ($x) => $x[1], $scored);
    }

    /** 0 = no match; higher = more specific. @param list<array<string,mixed>> $conditions */
    public function score(array $conditions, Request $request): int
    {
        $score = 0;

        foreach ($conditions as $rule) {
            $hit = $this->matches($rule, $request);
            if ($hit === null) {
                continue;
            }
            if (! empty($rule['exclude'])) {
                if ($hit) {
                    return 0;
                }

                continue;
            }
            if ($hit) {
                $score = max($score, ($rule['type'] ?? '') === 'all' ? 1 : 3);
            }
        }

        return $score;
    }

    /** @param array<string,mixed> $rule */
    private function matches(array $rule, Request $request): ?bool
    {
        $value = (string) ($rule['value'] ?? '');

        return match ($rule['type'] ?? null) {
            'all' => true,
            'path' => $value !== '' ? $request->is(ltrim($value, '/')) : null,
            'route' => $value !== '' ? (bool) $request->routeIs($value) : null,
            'url' => $value !== '' ? rtrim($request->fullUrl(), '/') === rtrim($value, '/') : null,
            default => null,
        };
    }
}
