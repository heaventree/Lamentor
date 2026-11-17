<?php

namespace Lamentor\Reviews;

/**
 * Named review feeds for the Reviews widget. Lamentor ships none: the host application registers
 * its own (for example a Google Business Profile feed it fetches and caches), in a service provider:
 *
 *   app(ReviewSources::class)->register('google', 'Google reviews', fn () => [
 *       'summary' => ['name' => 'Acme', 'rating' => 4.8, 'count' => 112, 'url' => '...', 'write_url' => '...', 'platform' => 'google'],
 *       'reviews' => [['author' => 'Ann B.', 'avatar' => 'https://...', 'rating' => 5, 'text' => '...', 'date' => '2026-11-01', 'url' => '...']],
 *   ]);
 *
 * The callable runs on every render of a widget that uses it, so it should read stored data, not call a remote API.
 */
final class ReviewSources
{
    /** @var array<string,array{label:string,source:callable():array}> */
    private array $sources = [];

    /** @param callable():array $source */
    public function register(string $name, string $label, callable $source): void
    {
        if ($name === '' || $name === 'manual') {
            throw new \InvalidArgumentException('A review source needs a name other than "manual".');
        }

        $this->sources[$name] = ['label' => $label, 'source' => $source];
    }

    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    /** @return array<string,string> name => label */
    public function labels(): array
    {
        return array_map(fn (array $s) => $s['label'], $this->sources);
    }

    /**
     * The feed of one source, normalised. An unknown source or a failing callable gives an empty feed.
     *
     * @return array{summary:array{name:string,rating:?float,count:?int,url:string,write_url:string,platform:string},reviews:list<array{author:string,avatar:string,rating:int,text:string,date:string,url:string}>}
     */
    public function feed(string $name): array
    {
        $raw = [];
        if (isset($this->sources[$name])) {
            try {
                $raw = (array) ($this->sources[$name]['source'])();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return self::normalise($raw);
    }

    /** @return array{summary:array,reviews:list<array>} */
    public static function normalise(array $raw): array
    {
        $summary = (array) ($raw['summary'] ?? []);
        $rating = $summary['rating'] ?? null;
        $count = $summary['count'] ?? null;

        $reviews = [];
        foreach ((array) ($raw['reviews'] ?? []) as $review) {
            $review = (array) $review;
            $reviews[] = [
                'author' => trim((string) ($review['author'] ?? '')),
                'avatar' => (string) ($review['avatar'] ?? ''),
                'rating' => max(0, min(5, (int) round((float) ($review['rating'] ?? 0)))),
                'text' => trim((string) ($review['text'] ?? '')),
                'date' => (string) ($review['date'] ?? ''),
                'url' => (string) ($review['url'] ?? ''),
            ];
        }

        return [
            'summary' => [
                'name' => trim((string) ($summary['name'] ?? '')),
                'rating' => is_numeric($rating) ? max(0.0, min(5.0, (float) $rating)) : null,
                'count' => is_numeric($count) ? max(0, (int) $count) : null,
                'url' => (string) ($summary['url'] ?? ''),
                'write_url' => (string) ($summary['write_url'] ?? ''),
                'platform' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($summary['platform'] ?? ''))),
            ],
            'reviews' => $reviews,
        ];
    }
}
