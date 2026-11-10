# Lamentor: open-source page builder for Laravel

[![Tests](https://github.com/heaventree/Lamentor/actions/workflows/tests.yml/badge.svg)](https://github.com/heaventree/Lamentor/actions/workflows/tests.yml)
[![License: GPL v3+](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
![PHP 8.2+](https://img.shields.io/badge/php-8.2%2B-777bb4)
![Laravel 11 | 12](https://img.shields.io/badge/laravel-11%20%7C%2012-ff2d20)

**Lamentor is a free, self-hosted drag-and-drop website builder for Laravel.** It renders and edits pages in the
Elementor document format (`_elementor_data` JSON), so you can reuse Elementor templates and exports in a Laravel
app, with a visual editor, responsive controls, forms, popups, theme templates and dynamic content. No WordPress needed.

> Looking for an **Elementor alternative for Laravel**, a **Laravel page builder**, or a way to **render Elementor
> pages outside WordPress**? That is what this is for.

Lamentor is not affiliated with or endorsed by Elementor (see [NOTICE](NOTICE.md)); "Elementor" is used only to describe compatibility.

## Why Lamentor

- **Elementor-compatible output.** Controls, defaults and CSS rules are generated from Elementor's own widget definitions, so pages look the same.
- **Native Laravel.** Eloquent model, Blade components, migrations, artisan commands, gates for permissions, Laravel mail and validation for forms.
- **Visual editor included.** Drag-and-drop canvas, settings for every control, navigator, undo/redo, tablet/mobile preview, revisions. No build step.
- **Server-rendered and fast.** 300 widgets render in about half a second; the front end uses Elementor's lightweight scripts.
- **Safe by default.** Allowlist HTML sanitizing, CSS value filtering, validated element trees, throttled public forms.
- **Open source, GPL-3.0-or-later.**

**Keywords:** Laravel page builder, Elementor for Laravel, drag and drop website builder, visual editor, WYSIWYG, landing page builder, headless CMS pages, Blade, responsive design, no-code.

## What works

| Area | State |
|---|---|
| Document model | Reads/writes Elementor element trees (container, legacy section/column, widgets), template import/export |
| Widgets (27 + Form, Loop Grid, Nav Menu) | heading, text-editor, button, image, icon, icon-box, icon-list, image-box, image-carousel, image-gallery, divider, spacer, html, alert, counter, progress, rating, star-rating, social-icons, testimonial, tabs, accordion, toggle, video, audio, google_maps, menu-anchor |
| Containers | Flex and grid containers, legacy sections and columns |
| Styling | Every style control of every ported widget, generated from Elementor's own control definitions: responsive (mobile/tablet + optional extra breakpoints), hover, typography, borders, shadows, backgrounds, global colours/typography from the Kit |
| Front end | Elementor's own CSS and JavaScript handlers (tabs, accordion, counter, carousel, lightbox ...) |
| Editor | Settings panel for all control types, live server-rendered canvas, drag and drop, navigator, undo/redo, device preview, revisions, media upload, block library (insert ready-made blocks, save any element as a block) |
| Pro-style modules (original) | **Form** widget (validation from stored fields, honeypot, throttling, stored submissions, optional e-mail) and **theme templates** and **popups** (display conditions, triggers), **dynamic tags**, **Loop Grid** (repeat a template per data item), **Nav Menu** with dropdown/mega panels, sticky + scroll-reveal controls on every element |
| Laravel | Eloquent model + migration, Blade component, JSON API, artisan commands, authorization gate |

### Not done (yet)

- Elementor Pro's motion effects beyond sticky and CSS scroll-reveal, form integrations (Mailchimp etc.), WooCommerce widgets (there is no WooCommerce in Laravel), theme-builder dynamic tags for WordPress data.
- Elementor 4's "atomic" widgets/editor (a separate data model).
- Shape dividers, video/slideshow backgrounds, uploaded-SVG inlining, attachment sizes/captions (images are addressed by URL).
- WordPress-only widgets (shortcode, sidebar, read-more, WordPress widgets/menus).
- Inline (on-canvas) text editing: text is edited in the panel.
- The canvas shows static markup: tabs/accordions/carousels animate on the real page, not inside the editor.

## Install

```bash
composer require lamentor/lamentor          # once published; for now use a path repository
php artisan migrate
php artisan vendor:publish --tag=lamentor-config
```

Lamentor renders against Elementor's static CSS/JS/fonts. They are GPLv3 files that are **not bundled**
(too large, and they should come from the real plugin so you can upgrade them):

```bash
# download the free Elementor plugin from wordpress.org, unzip it, then:
php artisan lamentor:install-assets /path/to/elementor
```

This copies `css/ js/ lib/ images/ shapes/` into `public/vendor/lamentor/elementor/assets`
(plus jQuery and jQuery UI Position, which ship with this package). The schemas in `resources/schema`
were generated from Elementor **4.3.6**; use that version's assets.

## Use

Render a stored document anywhere in Blade:

```blade
<x-lamentor::document :document="$page" />
```

or split head and body (recommended):

```blade
<head> <x-lamentor::head :document="$page" /> </head>
<body>
    <x-lamentor::document :document="$page" :head="false" />
    @include('lamentor::partials.frontend-scripts', ['document' => $page->render()])
</body>
```

From code:

```php
$rendered = app(\Lamentor\Rendering\Renderer::class)->document($elementorDataJsonOrArray, $pageId);
$rendered->html;        // markup
$rendered->css;         // kit variables + the document's rules
$rendered->stylesheets; // static files to link, relative to <assets_url>/assets/
$rendered->fontsUrl;    // Google Fonts URL (null if none needed)
```

Or via the model: `Lamentor\Models\Document::find($id)->render()`.

### Forms and theme templates

These two modules are original Lamentor designs written from Elementor's *documented behaviour*, not ported from Elementor Pro.

- Form widget: drop it in the editor. Only **published** documents accept submissions (`POST /lamentor/forms/{document}/{element}`, throttled; field rules always come from the stored document). Submissions go to `lamentor_form_submissions`; set "Send to" to also e-mail them (Laravel mail must be configured). Submitter IPs are not stored unless `lamentor.forms.store_ip` is true.
- Theme templates: create documents with type `header`, `footer`, `single`, `archive`, `404` or `section`, status `publish`, and conditions in `settings.conditions` (`all`, `path`, `route`, `url`, plus `exclude`). Print the match in your layout:

```blade
<x-lamentor::template location="header" />
```

- Popups: documents of type `popup` with `settings` `{conditions, trigger: load|delay|click|exit, delay, selector, once}`; print them with `<x-lamentor::popups />` (focus-managed dialog, Esc to close, remembers dismissal).
- Dynamic tags: bind a setting with `settings.__dynamic__ = {"title": {"tag": "field", "settings": {"key": "name"}}}`. Built in: `text`, `site-title`, `current-date`, `request-url`, `user-name`, `field`. Add your own with `app(Lamentor\Dynamic\DynamicTags::class)->register(...)`.
- Loop Grid: set a data source (`app(Lamentor\Dynamic\LoopSources::class)->register('posts', fn (int $limit) => Post::latest()->limit($limit)->get())`) and an item template (a `loop-item` document using `field` tags).
- Nav Menu: items with dropdown links (`Label|URL` per line, only http(s)/relative/mailto/# kept) or a mega panel (a `section`/`mega-menu` document).

### Editor

Open `/lamentor/editor/{id}`. Routes sit behind the middleware in `config('lamentor.middleware')`
(`web`, `auth` by default); define the `lamentor.edit` gate to restrict who may edit:

```php
Gate::define('lamentor.edit', fn ($user) => $user->is_admin);
```

Import an Elementor export (`POST /lamentor/api/documents/import` with `{"template": {...}}`), or check a
file without a database:

```bash
php artisan lamentor:render exported-template.json --out=check.html
```

#### Block library

The editor's **Library** tab lists ready-made blocks and inserts one after the selected element (or at the end).
The ★ button on an element's toolbar saves it, and everything inside it, as a block. Blocks come from sources:

- **Saved blocks**: documents of type `section`/`container` in this app (what ★ creates).
- **A folder of block files**, if `lamentor.library.directory` is set: `<category>/<slug>/block.json` (an Elementor
  export or bare element list), optional `meta.json` (`{"title": "...", "tags": [...]}`) and `thumb.webp|png|jpg`.
- **Your own sources**: implement `Lamentor\Library\LibrarySource` (a remote block service, for example) and list
  the class in `lamentor.library.sources`. A source that fails is reported in the panel without hiding the others.

Every inserted block gets fresh element ids and passes the same tree validation as a saved document.

**Security note.** The `html` widget prints author-supplied markup verbatim (as Elementor does), so only
trust people who can edit documents. Other widgets sanitize HTML (allowlist), URLs and CSS values.

## How it works

```
Elementor PHP classes ──(tools/dump-schema, runs them headlessly)──► resources/schema/*.json
                                                                         │ controls, defaults, selectors, conditions
saved element tree ──► Widget::render()  (HTML)        ◄─────────────────┤
                  └──► StyleGenerator    (CSS)         ◄─────────────────┘   port of Elementor's CSS pipeline
```

- `src/Css/StyleGenerator.php` is a port of Elementor's `Core\Files\CSS\Base` (placeholders, device queries,
  conditions, repeaters, globals) so output matches Elementor's.
- Widgets only implement `render()`; controls and style selectors come from the schema.
- Regenerate the schema from another Elementor version (needs PHP, no WordPress):

```bash
PHP=php bash tools/dump-schema/run.sh /path/to/elementor resources/schema
```

## Development

```bash
composer install
vendor/bin/phpunit
```

Try the editor without a Laravel app (Testbench-backed demo, authentication disabled, never expose it):

```bash
php vendor/bin/testbench lamentor:install-assets /path/to/elementor
php -S 127.0.0.1:8100 -t vendor/orchestra/testbench-core/laravel/public tools/demo/server.php
# open http://127.0.0.1:8100/?new=1
```

## Credits and licensing

See [NOTICE](NOTICE.md). Do not copy code from Elementor Pro or from any copy that contains licence
bypass code; port from the free plugin and from sources whose licence you have checked.
