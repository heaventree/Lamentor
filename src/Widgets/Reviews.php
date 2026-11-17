<?php

namespace Lamentor\Widgets;

use Illuminate\Support\Carbon;
use Lamentor\Elements\Element;
use Lamentor\Rendering\Renderer;
use Lamentor\Reviews\ReviewSources;

/**
 * Customer reviews as a grid, masonry wall, slider, list or a compact rating badge, with a rating
 * summary and a "Write a review" button. Reviews are typed into the widget, or come from a named
 * source the host application registers (see ReviewSources), such as a Google Business Profile
 * feed. Lamentor's own widget (not part of Elementor): plain HTML, styled by
 * css/lamentor-reviews.css; js/lamentor-reviews.js runs the slider and the "Read more" links.
 */
class Reviews extends Widget
{
    public static function name(): string
    {
        return 'lamentor-reviews';
    }

    private const LAYOUTS = ['grid', 'masonry', 'slider', 'list', 'badge'];

    private const THEMES = ['light', 'dark', 'outline', 'plain'];

    /** The source list in the editor also offers every registered source. */
    public function controls(): array
    {
        $controls = parent::controls();
        if (isset($controls['source']['options'])) {
            $controls['source']['options'] += app(ReviewSources::class)->labels();
        }

        return $controls;
    }

    protected function render(array $settings, Element $element, Renderer $renderer): string
    {
        $feed = $this->feed($settings);
        $summary = $feed['summary'];
        $all = $feed['reviews'];

        $layout = in_array($settings['layout'] ?? '', self::LAYOUTS, true) ? $settings['layout'] : 'grid';
        $theme = in_array($settings['theme'] ?? '', self::THEMES, true) ? $settings['theme'] : 'light';

        $rated = array_values(array_filter($all, fn (array $r) => $r['rating'] > 0));
        $rating = $summary['rating'] ?? ($rated ? array_sum(array_column($rated, 'rating')) / count($rated) : null);
        $count = $summary['count'] ?? count($all);

        $reviews = $this->select($all, $settings);
        if (! $reviews && ($layout !== 'badge' || $rating === null)) {
            return '';
        }

        $classes = ['lm-reviews', "lm-rv-layout-{$layout}", "lm-rv-theme-{$theme}", ($settings['shadow'] ?? 'yes') === 'yes' ? 'lm-rv-shadow' : null, ($settings['align'] ?? 'left') === 'center' ? 'lm-rv-align-center' : null];
        $platform = ($settings['show_platform'] ?? 'yes') === 'yes' ? $summary['platform'] : '';

        if ($layout === 'badge') {
            return $this->el('div', ['class' => $classes], $this->badge($summary, $rating, (int) $count, $platform, $settings));
        }

        $header = ($settings['show_header'] ?? 'yes') === 'yes' && $rating !== null
            ? $this->header($summary, $rating, (int) $count, $platform, $settings)
            : (($settings['show_write_button'] ?? 'yes') === 'yes' ? $this->writeButton($summary, $settings, 'lm-rv-write-solo') : '');

        $cards = '';
        foreach ($reviews as $i => $review) {
            $cards .= $this->card($review, $platform, $settings, $i);
        }

        if ($layout === 'slider') {
            $body = $this->el('div', ['class' => 'lm-rv-viewport'], $this->el('div', ['class' => 'lm-rv-track', 'tabindex' => '0', 'aria-label' => 'Reviews'], $cards));
            if (count($reviews) > 1) {
                if (($settings['arrows'] ?? 'yes') === 'yes') {
                    $body .= '<button type="button" class="lm-rv-arrow lm-rv-prev" aria-label="Previous reviews"><span aria-hidden="true">&#8249;</span></button>'
                        . '<button type="button" class="lm-rv-arrow lm-rv-next" aria-label="Next reviews"><span aria-hidden="true">&#8250;</span></button>';
                }
                if (($settings['dots'] ?? 'yes') === 'yes') {
                    $body .= '<div class="lm-rv-dots"></div>';
                }
            }
            $body = $this->el('div', ['class' => 'lm-rv-slider', 'role' => 'region', 'aria-roledescription' => 'carousel', 'aria-label' => 'Customer reviews'], $body);
        } else {
            $body = $this->el('div', ['class' => 'lm-rv-items'], $cards);
        }

        return $this->el('div', ['class' => $classes], $header . $body);
    }

    /** @return array{summary:array,reviews:list<array>} */
    private function feed(array $settings): array
    {
        $source = (string) ($settings['source'] ?? 'manual');
        $sources = app(ReviewSources::class);
        if ($source !== 'manual' && $sources->has($source)) {
            return $sources->feed($source);
        }

        $reviews = [];
        foreach ($this->items('reviews', $settings) as $item) {
            $reviews[] = [
                'author' => $item['author'] ?? '',
                'avatar' => $item['avatar']['url'] ?? '',
                'rating' => $item['rating'] ?? 5,
                'text' => $item['text'] ?? '',
                'date' => $item['date'] ?? '',
                'url' => '',
            ];
        }

        return ReviewSources::normalise([
            'summary' => [
                'name' => $settings['business_name'] ?? '',
                'rating' => ($settings['summary_rating'] ?? '') === '' ? null : $settings['summary_rating'],
                'count' => ($settings['summary_count'] ?? '') === '' ? null : $settings['summary_count'],
                'url' => $settings['reviews_link']['url'] ?? '',
                'write_url' => $settings['write_link']['url'] ?? '',
                'platform' => ($settings['platform'] ?? 'google') === 'none' ? '' : (string) ($settings['platform'] ?? 'google'),
            ],
            'reviews' => $reviews,
        ]);
    }

    /** Filter, sort and cap the reviews as the settings ask. @return list<array> */
    private function select(array $reviews, array $settings): array
    {
        $min = (int) ($settings['min_rating'] ?? 0);
        $withText = ($settings['only_with_text'] ?? 'yes') === 'yes';

        $reviews = array_values(array_filter($reviews, fn (array $r) => $r['author'] !== ''
            && ($min === 0 || $r['rating'] >= $min)
            && (! $withText || $r['text'] !== '')));

        $order = $settings['order'] ?? 'newest';
        if ($order === 'newest') {
            $stamps = array_map(fn (array $r) => $this->timestamp($r['date']) ?? 0, $reviews);
            array_multisort($stamps, SORT_DESC, SORT_NUMERIC, array_keys($reviews), SORT_ASC, $reviews);
        } elseif ($order === 'highest') {
            $ratings = array_column($reviews, 'rating');
            array_multisort($ratings, SORT_DESC, SORT_NUMERIC, array_keys($reviews), SORT_ASC, $reviews);
        }

        return array_slice($reviews, 0, max(1, min(50, (int) ($settings['limit'] ?? 9) ?: 9)));
    }

    private function header(array $summary, float $rating, int $count, string $platform, array $settings): string
    {
        $name = $summary['name'] !== '' ? $this->el('strong', ['class' => 'lm-rv-business'], e($summary['name'])) : '';
        $countText = $this->countText($count);
        if ($summary['url'] !== '') {
            $countText = '<a ' . $this->attrs(['href' => $this->safeUrl($summary['url']), 'target' => '_blank', 'rel' => 'noopener']) . '>' . $countText . '</a>';
        }

        $main = $name
            . '<div class="lm-rv-score">'
            . $this->el('span', ['class' => 'lm-rv-number'], e(number_format($rating, 1)))
            . $this->stars($rating)
            . $this->el('span', ['class' => 'lm-rv-count'], $countText)
            . '</div>';

        $write = ($settings['show_write_button'] ?? 'yes') === 'yes' ? $this->writeButton($summary, $settings) : '';

        return $this->el('div', ['class' => 'lm-rv-header'], $this->platformMark($platform, 'lm-rv-header-logo') . $this->el('div', ['class' => 'lm-rv-header-main'], $main) . $write);
    }

    private function badge(array $summary, float $rating, int $count, string $platform, array $settings): string
    {
        $label = $platform === 'google' ? 'Google rating' : 'Customer rating';
        if ($summary['name'] !== '') {
            $label = $summary['name'];
        }

        $inner = $this->platformMark($platform, 'lm-rv-badge-logo')
            . '<span class="lm-rv-badge-text">'
            . $this->el('span', ['class' => 'lm-rv-badge-label'], e($label))
            . '<span class="lm-rv-score">' . $this->el('span', ['class' => 'lm-rv-number'], e(number_format($rating, 1))) . $this->stars($rating) . '</span>'
            . $this->el('span', ['class' => 'lm-rv-count'], $this->countText($count))
            . '</span>';

        $url = $this->safeUrl($summary['url']);
        $badge = $url !== ''
            ? '<a ' . $this->attrs(['class' => 'lm-rv-badge', 'href' => $url, 'target' => '_blank', 'rel' => 'noopener']) . '>' . $inner . '</a>'
            : $this->el('div', ['class' => 'lm-rv-badge'], $inner);

        $write = ($settings['show_write_button'] ?? 'yes') === 'yes' ? $this->writeButton($summary, $settings) : '';

        return $this->el('div', ['class' => 'lm-rv-badge-wrap'], $badge . $write);
    }

    private function card(array $review, string $platform, array $settings, int $index): string
    {
        $name = ($settings['name_format'] ?? 'full') === 'short' ? $this->shortName($review['author']) : $review['author'];
        $nameHtml = e($name);
        $url = $this->safeUrl($review['url']);
        if ($url !== '') {
            $nameHtml = '<a ' . $this->attrs(['href' => $url, 'target' => '_blank', 'rel' => 'nofollow noopener']) . '>' . $nameHtml . '</a>';
        }

        $avatar = '';
        if (($settings['show_avatar'] ?? 'yes') === 'yes') {
            $avatarUrl = $this->safeUrl($review['avatar']);
            $avatar = $avatarUrl !== ''
                ? '<img ' . $this->attrs(['class' => 'lm-rv-avatar', 'src' => $avatarUrl, 'alt' => '', 'width' => '44', 'height' => '44', 'loading' => 'lazy', 'referrerpolicy' => 'no-referrer']) . '>'
                : $this->el('span', ['class' => 'lm-rv-avatar lm-rv-initial', 'style' => '--lm-rv-hue:' . (crc32($review['author']) % 360), 'aria-hidden' => 'true'], e(mb_strtoupper(mb_substr($name, 0, 1))));
        }

        $date = '';
        if (($settings['show_date'] ?? 'yes') === 'yes' && $review['date'] !== '') {
            $stamp = $this->timestamp($review['date']);
            $date = $stamp !== null
                ? '<time class="lm-rv-date" datetime="' . e(date('Y-m-d', $stamp)) . '">' . e(date('j M Y', $stamp)) . '</time>'
                : $this->el('span', ['class' => 'lm-rv-date'], e($review['date']));
        }

        $head = $avatar
            . $this->el('div', ['class' => 'lm-rv-who'], $this->el('span', ['class' => 'lm-rv-name'], $nameHtml) . $date)
            . $this->platformMark($platform, 'lm-rv-card-logo');

        $body = $review['rating'] > 0 ? $this->stars($review['rating']) : '';
        if ($review['text'] !== '') {
            $body .= $this->text($review['text'], (int) ($settings['text_limit'] ?? 180), (string) ($settings['read_more_text'] ?? '') ?: 'Read more');
        }

        return $this->el('article', ['class' => 'lm-rv-card', 'data-index' => (string) $index], $this->el('div', ['class' => 'lm-rv-card-head'], $head) . $this->el('div', ['class' => 'lm-rv-card-body'], $body));
    }

    /** Review text, cut at a word boundary with a "Read more" toggle when it is longer than the limit. */
    private function text(string $text, int $limit, string $more): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text);
        if ($limit <= 0 || mb_strlen($text) <= $limit + 20) {
            return $this->el('p', ['class' => 'lm-rv-text'], nl2br(e($text), false));
        }

        $short = mb_substr($text, 0, $limit);
        $cut = mb_strrpos($short, ' ');
        if ($cut !== false && $cut > $limit * 0.6) {
            $short = mb_substr($short, 0, $cut);
        }
        $short = rtrim($short, " \n,.;:-") . '…';

        return $this->el('p', ['class' => 'lm-rv-text'],
            $this->el('span', ['class' => 'lm-rv-short'], nl2br(e($short), false))
            . $this->el('span', ['class' => 'lm-rv-full', 'hidden' => true], nl2br(e($text), false))
            . ' <button type="button" class="lm-rv-more" aria-expanded="false">' . e($more) . '</button>');
    }

    /** Five stars filled to the rating (fractions allowed), coloured by CSS. */
    private function stars(float $rating): string
    {
        $pct = round(max(0, min(5, $rating)) / 5 * 100, 1);
        $label = rtrim(rtrim(number_format($rating, 1), '0'), '.') . ' out of 5 stars';

        return $this->el('span', ['class' => 'lm-rv-stars', 'role' => 'img', 'aria-label' => $label, 'style' => "--lm-rv-fill:{$pct}%"]);
    }

    private function writeButton(array $summary, array $settings, string $extra = ''): string
    {
        $url = $this->safeUrl($summary['write_url']);
        if ($url === '') {
            return '';
        }
        $text = trim((string) ($settings['write_button_text'] ?? '')) ?: 'Write a review';

        return '<a ' . $this->attrs(['class' => ['lm-rv-write', $extra ?: null], 'href' => $url, 'target' => '_blank', 'rel' => 'noopener']) . '>' . e($text) . '</a>';
    }

    private function platformMark(string $platform, string $class): string
    {
        if ($platform !== 'google') {
            return '';
        }

        return '<span class="lm-rv-logo ' . $class . '" role="img" aria-label="Google">'
            . '<svg viewBox="0 0 48 48" width="24" height="24" aria-hidden="true" focusable="false">'
            . '<path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h11.8c-.5 2.8-2.1 5.1-4.4 6.7v5.5h7.1c4.2-3.8 6.6-9.5 6.6-16.2z"/>'
            . '<path fill="#34A853" d="M24 46c5.9 0 10.9-2 14.5-5.3l-7.1-5.5c-2 1.3-4.5 2.1-7.4 2.1-5.7 0-10.6-3.9-12.3-9.1H4.4v5.7C8 41.1 15.4 46 24 46z"/>'
            . '<path fill="#FBBC05" d="M11.7 28.2c-.4-1.3-.7-2.7-.7-4.2s.3-2.9.7-4.2v-5.7H4.4C2.9 17.1 2 20.4 2 24s.9 6.9 2.4 9.9l7.3-5.7z"/>'
            . '<path fill="#EA4335" d="M24 10.7c3.2 0 6.1 1.1 8.4 3.3l6.3-6.3C34.9 4.2 29.9 2 24 2 15.4 2 8 6.9 4.4 14.1l7.3 5.7c1.7-5.2 6.6-9.1 12.3-9.1z"/>'
            . '</svg></span>';
    }

    private function countText(int $count): string
    {
        return e('Based on ' . number_format($count) . ' ' . ($count === 1 ? 'review' : 'reviews'));
    }

    /** "Marcus Hale" becomes "Marcus H.". */
    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($parts) < 2) {
            return $name;
        }

        return $parts[0] . ' ' . mb_strtoupper(mb_substr((string) end($parts), 0, 1)) . '.';
    }

    private function timestamp(string $date): ?int
    {
        if (trim($date) === '') {
            return null;
        }
        try {
            return Carbon::parse($date)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    /** http(s) and site-relative addresses only. */
    private function safeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^(https?://|/(?!/))#i', $url) ? $url : '';
    }
}
