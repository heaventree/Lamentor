<?php

namespace Lamentor\Tests;

use Lamentor\LamentorServiceProvider;
use Lamentor\Rendering\Renderer;
use Lamentor\Reviews\ReviewSources;
use Lamentor\Support\Assets;
use Lamentor\Widgets\WidgetRegistry;
use Orchestra\Testbench\TestCase;

class ReviewsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LamentorServiceProvider::class];
    }

    private function doc(array $settings): string
    {
        return json_encode([[
            'id' => 'r1', 'elType' => 'widget', 'widgetType' => 'lamentor-reviews', 'settings' => $settings, 'elements' => [],
        ]]);
    }

    private function html(array $settings = []): string
    {
        return $this->app->make(Renderer::class)->renderJson($this->doc($settings));
    }

    public function test_default_widget_renders_a_grid_with_summary_and_stars(): void
    {
        $out = $this->html();

        $this->assertStringContainsString('elementor-widget-lamentor-reviews', $out);
        $this->assertStringContainsString('lm-rv-layout-grid', $out);
        $this->assertSame(3, preg_match_all('/<article class="lm-rv-card"/', $out));
        $this->assertStringContainsString('class="lm-rv-business">Our business<', $out);
        $this->assertStringContainsString('<span class="lm-rv-number">4.7</span>', $out); // average of 5, 5, 4
        $this->assertStringContainsString('Based on 3 reviews', $out);
        $this->assertStringContainsString('aria-label="5 out of 5 stars"', $out);
        $this->assertStringContainsString('aria-label="Google"', $out);
        // newest first: 28 Oct before 12 Oct
        $this->assertLessThan(strpos($out, 'Marcus Hale'), strpos($out, 'Aoife Byrne'));
        $this->assertStringContainsString('<time class="lm-rv-date" datetime="2026-10-28">28 Oct 2026</time>', $out);
    }

    public function test_filters_order_limit_short_names_and_read_more(): void
    {
        $long = str_repeat('Lovely work and a great team. ', 12);
        $out = $this->html([
            'min_rating' => '4', 'order' => 'highest', 'limit' => 2, 'name_format' => 'short', 'text_limit' => 60,
            'reviews' => [
                ['author' => 'Low Rater', 'rating' => '2', 'text' => 'meh'],
                ['author' => 'Ann Marie Byrne', 'rating' => '4', 'text' => 'Fine'],
                ['author' => 'Tom Kelly', 'rating' => '5', 'text' => $long],
                ['author' => 'No Text', 'rating' => '5', 'text' => ''],
            ],
        ]);

        $this->assertStringNotContainsString('Low', $out);
        $this->assertStringNotContainsString('No T', $out, 'reviews without text are skipped by default');
        $this->assertSame(2, preg_match_all('/<article class="lm-rv-card"/', $out));
        $this->assertLessThan(strpos($out, 'Ann B.'), strpos($out, 'Tom K.'));
        $this->assertStringContainsString('class="lm-rv-more"', $out);
        $this->assertStringContainsString('<span class="lm-rv-full" hidden>', $out);
        $this->assertStringContainsString('Based on 4 reviews', $out, 'the summary counts every review, not just the shown ones');
    }

    public function test_slider_and_badge_layouts(): void
    {
        $slider = $this->html(['layout' => 'slider', 'autoplay_speed' => 4000]);
        $this->assertStringContainsString('lm-rv-track', $slider);
        $this->assertStringContainsString('lm-rv-prev', $slider);
        $this->assertStringContainsString('lm-rv-dots', $slider);
        $this->assertStringContainsString('autoplay_speed', $slider);

        $badge = $this->html(['layout' => 'badge', 'reviews_link' => ['url' => 'https://g.page/r/abc'], 'write_link' => ['url' => 'https://g.page/r/abc/review']]);
        $this->assertStringContainsString('lm-rv-badge', $badge);
        $this->assertStringNotContainsString('lm-rv-card', $badge);
        $this->assertStringContainsString('href="https://g.page/r/abc"', $badge);
        $this->assertStringContainsString('class="lm-rv-write" href="https://g.page/r/abc/review"', $badge);
    }

    public function test_registered_source_feeds_the_widget_and_is_offered_in_the_editor(): void
    {
        $this->app->make(ReviewSources::class)->register('google', 'Google reviews', fn () => [
            'summary' => ['name' => 'Galway Bikes', 'rating' => 4.8, 'count' => 212, 'url' => 'https://maps.google.com/?cid=1', 'write_url' => 'https://search.google.com/local/writereview?placeid=x', 'platform' => 'google'],
            'reviews' => [
                ['author' => 'Siobhan Walsh', 'avatar' => 'https://lh3.googleusercontent.com/a/x', 'rating' => 5, 'text' => 'Great bikes', 'date' => '2026-11-01T10:00:00Z', 'url' => 'https://www.google.com/maps/contrib/1'],
            ],
        ]);

        $out = $this->html(['source' => 'google']);
        $this->assertStringContainsString('Galway Bikes', $out);
        $this->assertStringContainsString('Based on 212 reviews', $out);
        $this->assertStringContainsString('<span class="lm-rv-number">4.8</span>', $out);
        $this->assertStringContainsString('src="https://lh3.googleusercontent.com/a/x"', $out);
        $this->assertStringContainsString('href="https://www.google.com/maps/contrib/1"', $out);
        $this->assertStringNotContainsString('Aoife', $out, 'manual reviews are not mixed in');

        $controls = $this->app->make(WidgetRegistry::class)->widget('lamentor-reviews')->controls();
        $this->assertSame('Google reviews', $controls['source']['options']['google']);
    }

    public function test_a_failing_or_empty_source_renders_nothing(): void
    {
        $this->app->make(ReviewSources::class)->register('broken', 'Broken', fn () => throw new \RuntimeException('API down'));

        $this->assertStringNotContainsString('lm-reviews', $this->html(['source' => 'broken']));
        $missing = $this->html(['source' => 'missing']);
        $this->assertStringNotContainsString('lm-reviews', $missing, 'an unregistered source shows nothing');
        $this->assertStringNotContainsString('Aoife', $missing);
    }

    public function test_unsafe_input_is_neutralised(): void
    {
        $out = $this->html([
            'business_name' => '<img src=x onerror=alert(1)>',
            'write_link' => ['url' => 'javascript:alert(1)'],
            'reviews' => [['author' => '<b>x</b>', 'rating' => '9', 'text' => '<script>alert(1)</script>', 'avatar' => ['url' => 'javascript:alert(1)']]],
        ]);

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('<img src=x', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $out);
        $this->assertStringContainsString('aria-label="5 out of 5 stars"', $out, 'ratings are capped at 5');
    }

    public function test_assets_and_responsive_columns(): void
    {
        $renderer = $this->app->make(Renderer::class);

        $this->assertContains('css/lamentor-reviews.css', $renderer->stylesheetsForJson($this->doc([])));
        $scripts = Assets::scriptsFor($renderer->document($this->doc([]), 1));
        $this->assertContains('js/lamentor-reviews.js', $scripts['before']);

        $css = $renderer->cssForJson($this->doc(['columns' => '4', 'columns_tablet' => '2', 'columns_mobile' => '1']), 1);
        $this->assertStringContainsString('--lm-rv-cols:4', str_replace(' ', '', $css));
        $this->assertMatchesRegularExpression('/@media\(max-width:1024px\)\{[^}]*--lm-rv-cols:2/', str_replace(["\n", ' '], '', $css));
    }
    public function test_every_layout_renders_its_own_markup(): void
    {
        foreach (['grid', 'masonry', 'slider', 'list'] as $layout) {
            $out = $this->html(['layout' => $layout]);
            $this->assertStringContainsString("lm-rv-layout-{$layout}", $out, $layout);
            $this->assertSame(3, preg_match_all('/<article class="lm-rv-card"/', $out), $layout);
            $this->assertSame($layout === 'slider', str_contains($out, 'lm-rv-track'), $layout);
        }
        $badge = $this->html(['layout' => 'badge']);
        $this->assertStringContainsString('lm-rv-layout-badge', $badge);
        $this->assertStringNotContainsString('lm-rv-card', $badge);
        $this->assertStringContainsString('lm-rv-layout-grid', $this->html(['layout' => 'bogus']), 'an unknown layout falls back to grid');
    }

    public function test_each_order_and_filter_option(): void
    {
        $reviews = [
            ['author' => 'Middle', 'rating' => '4', 'text' => 'b', 'date' => '2026-05-01'],
            ['author' => 'Oldest', 'rating' => '5', 'text' => 'a', 'date' => '2025-01-01'],
            ['author' => 'Newest', 'rating' => '3', 'text' => 'c', 'date' => '2026-10-01'],
            ['author' => 'Silent', 'rating' => '5', 'text' => '', 'date' => '2026-11-01'],
        ];
        $names = function (array $settings) use ($reviews): array {
            preg_match_all('/class="lm-rv-name"[^>]*>([^<]+)</', $this->html($settings + ['reviews' => $reviews]), $m);

            return $m[1];
        };

        $this->assertSame(['Newest', 'Middle', 'Oldest'], $names(['order' => 'newest']));
        $this->assertSame(['Oldest', 'Middle', 'Newest'], $names(['order' => 'highest']));
        $this->assertSame(['Middle', 'Oldest', 'Newest'], $names(['order' => 'source']));
        $this->assertSame(['Silent', 'Newest', 'Middle', 'Oldest'], $names(['order' => 'newest', 'only_with_text' => '']));
        $this->assertSame(['Oldest'], $names(['min_rating' => '5']));
        $this->assertSame(['Oldest', 'Silent'], $names(['min_rating' => '5', 'only_with_text' => '', 'order' => 'source']));
        $this->assertSame(['Newest', 'Middle'], $names(['limit' => 2]));
    }

    public function test_filters_and_order_apply_to_a_registered_source_too(): void
    {
        $this->app->make(ReviewSources::class)->register('feed', 'Feed', fn () => ['summary' => ['rating' => 4.2, 'count' => 90], 'reviews' => [
            ['author' => 'Two Star', 'rating' => 2, 'text' => 'x', 'date' => '2026-11-10'],
            ['author' => 'Five Star', 'rating' => 5, 'text' => 'y', 'date' => '2026-01-10'],
            ['author' => 'Four Star', 'rating' => 4, 'text' => 'z', 'date' => '2026-06-10'],
        ]]);

        $out = $this->html(['source' => 'feed', 'min_rating' => '4', 'order' => 'newest']);
        $this->assertStringNotContainsString('Two Star', $out);
        $this->assertLessThan(strpos($out, 'Five Star'), strpos($out, 'Four Star'));
        $this->assertStringContainsString('Based on 90 reviews', $out, 'the source summary is kept');
    }

    public function test_assets_are_only_added_when_the_widget_is_on_the_page(): void
    {
        $renderer = $this->app->make(Renderer::class);
        $other = json_encode([['id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => 'Hi'], 'elements' => []]]);

        $this->assertNotContains('css/lamentor-reviews.css', $renderer->stylesheetsForJson($other));
        $scripts = Assets::scriptsFor($renderer->document($other, 1));
        $this->assertNotContains('js/lamentor-reviews.js', array_merge(...array_values($scripts)));

        // nested inside a container it is still found
        $nested = json_encode([['id' => 'c1', 'elType' => 'container', 'settings' => [], 'elements' => [
            ['id' => 'r1', 'elType' => 'widget', 'widgetType' => 'lamentor-reviews', 'settings' => [], 'elements' => []],
        ]]]);
        $this->assertContains('css/lamentor-reviews.css', $renderer->stylesheetsForJson($nested));
        $this->assertContains('js/lamentor-reviews.js', Assets::scriptsFor($renderer->document($nested, 1))['before']);
    }
}
