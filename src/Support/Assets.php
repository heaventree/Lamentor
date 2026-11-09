<?php

namespace Lamentor\Support;

use Lamentor\Elements\Element;
use Lamentor\Rendering\RenderedDocument;

/**
 * Static files a rendered document links to. All paths are relative to the
 * assets folder (`<assets_url>/assets/`), which `lamentor:install-assets` fills from
 * Elementor 4.3.6 (GPLv3) plus jQuery / jQuery UI (MIT). `css/frontend.min.css` holds
 * the base styles, including the container layout that the per-element CSS variables
 * plug into; most widgets also have their own `css/widget-{name}.min.css`.
 */
final class Assets
{
    public const BASE = 'css/frontend.min.css';

    /** Widgets with a separate `css/widget-{name}.min.css` (Elementor loads them conditionally). */
    private const WIDGET_STYLESHEETS = [
        'accordion', 'alert', 'counter', 'divider', 'google_maps', 'heading', 'icon-box', 'icon-list', 'image-box',
        'image-carousel', 'image-gallery', 'image', 'menu-anchor', 'progress', 'rating', 'social-icons', 'spacer',
        'star-rating', 'tabs', 'testimonial', 'text-editor', 'toggle', 'video',
    ];

    /** Icon library => stylesheets that provide its font. */
    private const ICON_LIBRARIES = [
        'fa-solid' => ['lib/font-awesome/css/fontawesome.min.css', 'lib/font-awesome/css/solid.min.css'],
        'fa-regular' => ['lib/font-awesome/css/fontawesome.min.css', 'lib/font-awesome/css/regular.min.css'],
        'fa-brands' => ['lib/font-awesome/css/fontawesome.min.css', 'lib/font-awesome/css/brands.min.css'],
    ];

    /** Elementor always loads its own icon font on the front end. */
    private const EICONS = 'lib/eicons/css/elementor-icons.min.css';

    /** Extra libraries a widget needs on top of the base scripts. */
    private const WIDGET_LIBS = [
        'image-carousel' => ['lib/swiper/v8/swiper.min.js'],
        'counter' => ['lib/jquery-numerator/jquery-numerator.min.js'],
        'image-gallery' => ['lib/dialog/dialog.min.js'],
        'video' => ['lib/dialog/dialog.min.js'],
        'image' => ['lib/dialog/dialog.min.js'],
    ];

    /** Folder that holds the stylesheets bundled in the package (same names as under css/). */
    public static function directory(): string
    {
        return __DIR__ . '/../../resources/css';
    }

    /**
     * Stylesheets a document needs, in order, relative to the assets folder.
     *
     * @param list<Element> $elements
     * @return list<string>
     */
    public static function stylesheetsFor(array $elements): array
    {
        $files = [self::BASE, self::EICONS];
        $add = static function (string $file) use (&$files): void {
            if (! in_array($file, $files, true)) {
                $files[] = $file;
            }
        };

        self::collect($elements, static function (Element $e) use ($add): void {
            if ($e->type === 'widget' && in_array($e->widgetType, self::WIDGET_STYLESHEETS, true)) {
                $add("css/widget-{$e->widgetType}.min.css");
            }

            self::scanSettings($e->settings, $add);
        });

        return $files;
    }

    /** Icon fonts and animation CSS implied by a settings array (recursing into repeaters). */
    private static function scanSettings(array $settings, callable $add): void
    {
        foreach ($settings as $key => $value) {
            if (! is_array($value)) {
                if (is_string($value) && $value !== '' && $value !== 'none') {
                    if ($key === 'lm_reveal') {
                        $add('css/lamentor.css');
                    } elseif (in_array($key, ['_animation', 'animation', 'animation_tablet', 'animation_mobile', '_animation_tablet', '_animation_mobile'], true)) {
                        $add('lib/animations/animations.min.css');
                    } elseif (in_array($key, ['hover_animation', '_hover_animation'], true) && preg_match('/^[a-z0-9-]+$/', $value)) {
                        $add("lib/animations/styles/e-animation-{$value}.min.css");
                    }
                }

                continue;
            }

            if (isset($value['library']) && is_string($value['library']) && isset(self::ICON_LIBRARIES[$value['library']])) {
                foreach (self::ICON_LIBRARIES[$value['library']] as $file) {
                    $add($file);
                }
            }

            self::scanSettings($value, $add);
        }
    }

    /**
     * Script paths (relative to the assets folder) in load order: `before` the
     * `elementorFrontendConfig` inline script and `after` it.
     *
     * @param list<Element>|RenderedDocument|null $document
     * @return array{before:list<string>,after:list<string>}
     */
    public static function scriptsFor(array|RenderedDocument|null $document = null): array
    {
        $libs = [];

        if (is_array($document)) {
            self::collect($document, static function (Element $e) use (&$libs) {
                foreach (self::WIDGET_LIBS[$e->widgetType ?? ''] ?? [] as $lib) {
                    $libs[$lib] = $lib;
                }
            });
        } elseif ($document instanceof RenderedDocument) {
            // Only the HTML is known: detect widgets from their wrapper classes.
            foreach (self::WIDGET_LIBS as $widget => $paths) {
                if (str_contains($document->html, "elementor-widget-{$widget}")) {
                    foreach ($paths as $lib) {
                        $libs[$lib] = $lib;
                    }
                }
            }
        }

        return [
            'before' => ['js/jquery.min.js', 'js/jquery-ui.min.js', 'js/webpack.runtime.min.js', 'js/frontend-modules.min.js', ...array_values($libs)],
            'after' => ['js/frontend.min.js'],
        ];
    }

    /** @param list<Element> $elements */
    private static function collect(array $elements, callable $visit): void
    {
        foreach ($elements as $element) {
            $visit($element);
            self::collect($element->children, $visit);
        }
    }
}
