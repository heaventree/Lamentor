<?php

namespace Lamentor\Widgets;

use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\Embed;

/** SoundCloud embed (WordPress resolves it via oEmbed; the player URL is built directly here). */
class Audio extends Widget
{
    public static function name(): string
    {
        return 'audio';
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $link = (string) ($settings['link']['url'] ?? '');
        if ($link === '' || ! preg_match('#^https?://(www\.)?(soundcloud\.com|snd\.sc)/#i', $link)) {
            return '';
        }

        $flag = fn (string $key) => ($settings[$key] ?? '') === 'yes' ? 'true' : 'false';

        $url = Embed::addQuery('https://w.soundcloud.com/player/', [
            'url' => $link,
            'visual' => ($settings['visual'] ?? '') === 'yes' ? 'true' : 'false',
            'auto_play' => $flag('sc_auto_play'),
            'buying' => $flag('sc_buy'),
            'liking' => $flag('sc_like'),
            'download' => $flag('sc_download'),
            'sharing' => $flag('sc_share'),
            'show_comments' => $flag('sc_comments'),
            'show_playcount' => $flag('sc_playcount'),
            'show_user' => $flag('sc_show_user'),
            'color' => ! empty($settings['sc_color']) ? ltrim((string) $settings['sc_color'], '#') : null,
        ]);

        return $this->el('div', ['class' => $this->prefix() . '-soundcloud-wrapper'],
            $this->el('iframe', ['width' => '100%', 'height' => '165', 'scrolling' => 'no', 'frameborder' => 'no', 'allow' => 'autoplay', 'src' => $url]));
    }
}
