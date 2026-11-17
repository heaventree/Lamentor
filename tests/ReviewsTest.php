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
}
