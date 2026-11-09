<?php

namespace Lamentor\Support;

/** Port of the URL handling in Elementor's Embed class (YouTube, Vimeo, Dailymotion, VideoPress). */
final class Embed
{
    private const MASKS = [
        'youtube' => ['/^.*(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:(?:watch)?\?(?:.*&)?vi?=|(?:embed|v|vi|user|shorts)\/))([^\?&"\'>]+)/'],
        'vimeo' => ['/^.*vimeo\.com\/(?:[a-z]*\/)*([0-9]{6,11})[?]?.*/'],
        'dailymotion' => ['/^.*dailymotion.com\/(?:video|hub)\/([^_]+)[^#]*(#video=([^_&]+))?/'],
        'videopress' => [
            '/^(?:http(?:s)?:\/\/)?videos\.files\.wordpress\.com\/([a-zA-Z\d]{8,})\//i',
            '/^(?:http(?:s)?:\/\/)?(?:www\.)?video(?:\.word)?press\.com\/(?:v|embed)\/([a-zA-Z\d]{8,})(.+)?/i',
        ],
    ];

    private const PATTERNS = [
        'youtube' => 'https://www.youtube{NO_COOKIE}.com/embed/{VIDEO_ID}?feature=oembed',
        'vimeo' => 'https://player.vimeo.com/video/{VIDEO_ID}#t={TIME}',
        'dailymotion' => 'https://dailymotion.com/embed/video/{VIDEO_ID}',
        'videopress' => 'https://videopress.com/embed/{VIDEO_ID}',
    ];

    /** @return array{provider:string,video_id:string}|null */
    public static function properties(string $url): ?array
    {
        foreach (self::MASKS as $provider => $masks) {
            foreach ($masks as $mask) {
                if (preg_match($mask, $url, $m)) {
                    return ['provider' => $provider, 'video_id' => $m[1]];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,string|int|null> $params query parameters (null/'' are dropped)
     * @param array{privacy?:bool,start?:int|string} $options
     */
    public static function url(string $videoUrl, array $params = [], array $options = []): ?string
    {
        $video = self::properties($videoUrl);
        if (! $video) {
            return null;
        }

        $replacements = ['{VIDEO_ID}' => $video['video_id']];

        if ($video['provider'] === 'youtube') {
            $replacements['{NO_COOKIE}'] = ! empty($options['privacy']) ? '-nocookie' : '';
        } elseif ($video['provider'] === 'vimeo') {
            $replacements['{TIME}'] = ! empty($options['start']) ? gmdate('H\hi\ms\s', (int) $options['start']) : '';
            if (preg_match('/(?|(?:[\?|\&]h={1})([\w]+)|\d\/([\w]+))/', $videoUrl, $h)) {
                $params['h'] = $h[1];
            }
        }

        return self::addQuery(strtr(self::PATTERNS[$video['provider']], $replacements), $params);
    }

    /** @param array<string,string|int|null> $params */
    public static function addQuery(string $url, array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        if (! $params) {
            return $url;
        }

        $fragment = '';
        if (($pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $fragment;
    }
}
