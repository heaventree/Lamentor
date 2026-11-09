<?php

namespace Lamentor\Support;

use Lamentor\Css\Breakpoints;
use Lamentor\Kit\Kit;

/**
 * The `elementorFrontendConfig` object Elementor's frontend script reads before
 * it starts (breakpoints, asset URL for lazily loaded handler chunks, lightbox
 * and carousel options ...).
 */
final class FrontendConfig
{
    private const BREAKPOINT_LABELS = [
        'mobile' => 'Mobile Portrait',
        'mobile_extra' => 'Mobile Landscape',
        'tablet' => 'Tablet Portrait',
        'tablet_extra' => 'Tablet Landscape',
        'laptop' => 'Laptop',
        'widescreen' => 'Widescreen',
    ];

    private const BREAKPOINT_DEFAULTS = ['mobile' => 767, 'mobile_extra' => 880, 'tablet' => 1024, 'tablet_extra' => 1200, 'laptop' => 1366, 'widescreen' => 2400];

    public function __construct(
        private readonly Breakpoints $breakpoints,
        private readonly Kit $kit,
        private readonly string $assetsUrl,
    ) {
    }

    /**
     * @param array{id?:int|string,title?:string,excerpt?:string,featuredImage?:string|false} $post
     * @return array<string,mixed>
     */
    public function make(array $post = []): array
    {
        $responsive = [];
        foreach (self::BREAKPOINT_LABELS as $name => $label) {
            $active = $this->breakpoints->value($name);
            $responsive[$name] = [
                'label' => $label,
                'value' => $active ?? self::BREAKPOINT_DEFAULTS[$name],
                'default_value' => self::BREAKPOINT_DEFAULTS[$name],
                'direction' => $this->breakpoints->direction($name),
                'is_enabled' => $active !== null,
            ];
        }

        return [
            'environmentMode' => ['edit' => false, 'wpPreview' => false, 'isScriptDebug' => false],
            'i18n' => [
                'shareOnFacebook' => 'Share on Facebook', 'shareOnX' => 'Share on X', 'pinIt' => 'Pin it',
                'download' => 'Download', 'downloadImage' => 'Download image', 'fullscreen' => 'Fullscreen',
                'zoom' => 'Zoom', 'share' => 'Share', 'playVideo' => 'Play Video', 'previous' => 'Previous',
                'next' => 'Next', 'close' => 'Close',
                'a11yCarouselPrevSlideMessage' => 'Previous slide', 'a11yCarouselNextSlideMessage' => 'Next slide',
                'a11yCarouselFirstSlideMessage' => 'This is the first slide', 'a11yCarouselLastSlideMessage' => 'This is the last slide',
                'a11yCarouselPaginationBulletMessage' => 'Go to slide',
            ],
            'is_rtl' => (bool) config('lamentor.rtl', false),
            'breakpoints' => ['xs' => 0, 'sm' => 480, 'md' => 768, 'lg' => 1025, 'xl' => 1440, 'xxl' => 1600],
            'responsive' => ['breakpoints' => $responsive, 'hasCustomBreakpoints' => false],
            'version' => '4.3.6',
            'is_static' => false,
            'experimentalFeatures' => ['container' => true, 'e_swiper_latest' => true],
            'urls' => ['assets' => rtrim($this->assetsUrl, '/') . '/', 'ajaxurl' => '', 'uploadUrl' => ''],
            'nonces' => (object) [],
            'swiperClass' => 'swiper',
            'settings' => ['page' => (object) [], 'editorPreferences' => (object) []],
            'kit' => $this->kitSettings(),
            'post' => [
                'id' => $post['id'] ?? 0,
                'title' => $post['title'] ?? '',
                'excerpt' => $post['excerpt'] ?? '',
                'featuredImage' => $post['featuredImage'] ?? false,
            ],
        ];
    }

    /** Kit settings the frontend script reads (lightbox behaviour). @return array<string,mixed> */
    private function kitSettings(): array
    {
        $settings = $this->kit->settings();

        return array_replace([
            'global_image_lightbox' => 'yes',
            'lightbox_enable_counter' => 'yes',
            'lightbox_enable_fullscreen' => 'yes',
            'lightbox_enable_zoom' => 'yes',
            'lightbox_enable_share' => 'yes',
            'lightbox_title_src' => 'title',
            'lightbox_description_src' => 'description',
        ], array_intersect_key($settings, array_flip([
            'global_image_lightbox', 'lightbox_enable_counter', 'lightbox_enable_fullscreen', 'lightbox_enable_zoom',
            'lightbox_enable_share', 'lightbox_title_src', 'lightbox_description_src',
        ])));
    }

    /** JSON safe to embed inside an inline <script>. */
    public function json(array $post = []): string
    {
        return json_encode($this->make($post), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
