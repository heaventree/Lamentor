<?php

return [
    // Prefix for generated CSS classes. "elementor" keeps compatibility with
    // existing Elementor stylesheets; change it once Lamentor ships its own CSS.
    'class_prefix' => 'elementor',

    // URL under which Elementor's static assets are served (see `php artisan lamentor:install-assets`).
    'assets_url' => '/vendor/lamentor/elementor',

    // Register the editor/preview/API routes. Put them behind your own auth/permissions.
    'routes' => true,
    'route_prefix' => 'lamentor',
    'middleware' => ['web', 'auth'],

    // Where the editor's media uploads go, and the size cap in KB.
    'media_disk' => 'public',
    'media_max_kb' => 10240,

    // Link shown as "back" in the editor top bar (null hides it).
    'editor_back_url' => null,

    // Form widget: middleware for the public submit route, e-mail sending, and whether to keep submitter IPs.
    'forms_middleware' => ['web', 'throttle:10,1'],
    'forms' => ['mail' => true, 'store_ip' => false],

    // Revisions kept per document.
    'max_revisions' => 20,

    // Extra font families that need no Google Fonts request (self-hosted, etc.).
    'system_fonts' => [],
    'font_display' => 'auto',

    // Optional Google Maps embed API key for the Google Maps widget.
    'google_maps_api_key' => env('LAMENTOR_GOOGLE_MAPS_KEY', ''),

    // Global colours/typography: see Kit::defaults() for the shape. Empty = Elementor's defaults.
    'kit' => [],
    'kit_id' => 1,

    // Max-width breakpoints in px (Elementor defaults). Desktop is the base.
    'breakpoints' => ['tablet' => 1024, 'mobile' => 767],
];
