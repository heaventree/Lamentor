<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Embed;
use Lamentor\Support\Icons;

/**
 * YouTube is built client-side by Elementor's frontend script (the widget only
 * outputs the placeholder + data-settings); Vimeo, Dailymotion and hosted
 * files are rendered as iframe / <video> directly. Lightbox playback also
 * needs that script.
 */
class Video extends Widget
{
    public static function name(): string
    {
        return 'video';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $p = $this->prefix();
        $type = (string) ($settings['video_type'] ?? 'youtube');

        $videoUrl = $type === 'hosted' ? $this->hostedUrl($settings) : (string) ($settings[$type . '_url'] ?? '');
        if ($videoUrl === '') {
            return '';
        }

        $hasOverlay = ! empty($settings['image_overlay']['url']) && ($settings['show_image_overlay'] ?? '') === 'yes';
        $params = $type === 'hosted' ? [] : $this->embedParams($settings, $type, $hasOverlay);
        $options = ['privacy' => ! empty($settings['yt_privacy']), 'start' => $settings['start'] ?? null];

        $lightbox = ! empty($settings['lightbox']);
        $wrapperClass = [$type === 'hosted' ? 'e-hosted-video' : null, "{$p}-wrapper", "{$p}-open-" . ($lightbox ? 'lightbox' : 'inline')];

        if ($type === 'hosted') {
            $videoHtml = $this->hostedVideo($settings, $videoUrl);
        } elseif ($type === 'youtube') {
            $videoHtml = "<div class=\"{$p}-video\"></div>";
        } else {
            $embedUrl = Embed::url($videoUrl, $params, $options);
            $videoHtml = $embedUrl ? $this->el('iframe', array_filter([
                'class' => "{$p}-video-iframe",
                'allowfullscreen' => true,
                'allow' => isset($params['autoplay']) ? 'autoplay' : 'clipboard-write',
                'title' => ucfirst($type) . ' Video Player',
                empty($settings['lazy_load']) ? 'src' : 'data-lazy-load' => $embedUrl,
            ], fn ($v) => $v !== null)) : '';
        }

        if ($videoHtml === '') {
            return e($videoUrl);
        }

        $out = $lightbox ? '' : $videoHtml;

        if ($hasOverlay) {
            $overlay = ['class' => "{$p}-custom-embed-image-overlay"];
            $inner = '';

            if ($lightbox) {
                $lightboxUrl = $type === 'hosted' ? $videoUrl : Embed::url($videoUrl, $params, $options);
                $overlay['data-elementor-open-lightbox'] = 'yes';
                $overlay['data-elementor-lightbox'] = json_encode([
                    'type' => 'video',
                    'videoType' => $type,
                    'url' => $lightboxUrl,
                    'autoplay' => $settings['autoplay'] ?? '',
                    'modalOptions' => [
                        'id' => "{$p}-lightbox-" . $element->id,
                        'entranceAnimation' => $settings['lightbox_content_animation'] ?? '',
                        'videoAspectRatio' => $settings['aspect_ratio'] ?? '169',
                    ],
                ], JSON_UNESCAPED_SLASHES);
                $inner .= $this->imageHtml($settings['image_overlay'], (string) ($settings['image_overlay_size'] ?? 'full'));
            } else {
                $overlay['style'] = 'background-image: url(' . ($settings['image_overlay']['url']) . ');';
            }

            if (($settings['show_play_icon'] ?? '') === 'yes') {
                $icon = Icons::render($settings['play_icon'] ?? []) ?: Icons::render(['library' => 'eicons', 'value' => 'eicon-play']);
                $inner .= $this->el('div', ['class' => "{$p}-custom-embed-play", 'role' => 'button', 'aria-label' => 'Play Video', 'tabindex' => '0'], $icon);
            }

            $out .= $this->el('div', $overlay, $inner);
        }

        return $this->el('div', ['class' => $wrapperClass], $out);
    }

    /** @return array<string,string> */
    private function embedParams(array $settings, string $type, bool $hasOverlay): array
    {
        $params = [];
        $flag = fn (string $key) => ! empty($settings[$key]) ? '1' : '0';

        if (! empty($settings['autoplay']) && ! $hasOverlay) {
            $params['autoplay'] = '1';
            if (! empty($settings['play_on_mobile'])) {
                $params['playsinline'] = '1';
            }
        }

        if ($type === 'youtube') {
            foreach (['loop', 'controls', 'mute', 'rel', 'cc_load_policy'] as $k) {
                $params[$k] = $flag($k);
            }
            if (! empty($settings['loop'])) {
                $params['playlist'] = Embed::properties((string) ($settings['youtube_url'] ?? ''))['video_id'] ?? '';
            }
            $params['start'] = (string) ($settings['start'] ?? '');
            $params['end'] = (string) ($settings['end'] ?? '');
            $params['wmode'] = 'opaque';
        } elseif ($type === 'vimeo') {
            $params['loop'] = $flag('loop');
            $params['muted'] = $flag('mute');
            $params['title'] = $flag('vimeo_title');
            $params['portrait'] = $flag('vimeo_portrait');
            $params['byline'] = $flag('vimeo_byline');
            $params['color'] = str_replace('#', '', (string) ($settings['color'] ?? ''));
            $params['autopause'] = '0';
            if (! empty($settings['yt_privacy'])) {
                $params['dnt'] = 'true';
            }
        } elseif ($type === 'dailymotion') {
            $params['controls'] = $flag('controls');
            $params['mute'] = $flag('mute');
            $params['ui-start-screen-info'] = $flag('showinfo');
            $params['ui-logo'] = $flag('logo');
            $params['ui-highlight'] = str_replace('#', '', (string) ($settings['color'] ?? ''));
            $params['start'] = (string) ($settings['start'] ?? '');
            $params['endscreen-enable'] = '0';
        }

        return $params;
    }

    private function hostedUrl(array $settings): string
    {
        $url = ! empty($settings['insert_url']) ? ($settings['external_url']['url'] ?? '') : ($settings['hosted_url']['url'] ?? '');
        if ($url === '') {
            return '';
        }

        $start = $settings['start'] ?? '';
        $end = $settings['end'] ?? '';
        if ($start || $end) {
            $url .= '#t=' . ($start ?: '') . ($end ? ',' . $end : '');
        }

        return $url;
    }

    private function hostedVideo(array $settings, string $url): string
    {
        $attrs = ['class' => $this->prefix() . '-video', 'src' => $url];

        foreach (['autoplay', 'loop', 'controls'] as $flag) {
            if (! empty($settings[$flag])) {
                $attrs[$flag] = '';
            }
        }
        if (! empty($settings['preload'])) {
            $attrs['preload'] = $settings['preload'];
        }
        if (! empty($settings['mute'])) {
            $attrs['muted'] = 'muted';
        }
        if (! empty($settings['play_on_mobile'])) {
            $attrs['playsinline'] = '';
        }
        if (empty($settings['download_button'])) {
            $attrs['controlsList'] = 'nodownload';
        }
        if (! empty($settings['poster']['url'])) {
            $attrs['poster'] = $settings['poster']['url'];
        }

        return $this->el('video', $attrs);
    }
}
