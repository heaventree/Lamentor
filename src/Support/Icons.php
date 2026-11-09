<?php

namespace Lamentor\Support;

/**
 * Renders Elementor icon-control values: {value: 'fas fa-star', library: 'fa-solid'}.
 *
 * Font libraries (Font Awesome, eicons, custom icon fonts) become <i> tags, so
 * the page must load that font CSS. Uploaded SVGs (library "svg", value {url})
 * are emitted as an <img>; Elementor inlines the file instead, which needs a
 * file resolver that Lamentor does not have yet.
 */
final class Icons
{
    public static function render(array $icon, bool $ariaHidden = true): string
    {
        $library = $icon['library'] ?? '';
        $value = $icon['value'] ?? '';

        if ($library === '' || $value === '' || $value === []) {
            return '';
        }

        if ($library === 'svg') {
            $url = is_array($value) ? (string) ($value['url'] ?? '') : '';
            if ($url === '' || preg_match('/^(javascript|vbscript|data):/i', preg_replace('/[\x00-\x20]+/', '', $url))) {
                return '';
            }

            return '<img src="' . e($url) . '" alt="" aria-hidden="true">';
        }

        if (! is_string($value)) {
            return '';
        }

        // Class names only: never let a saved value break out of the attribute.
        $classes = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $value));

        return '<i' . ($ariaHidden ? ' aria-hidden="true"' : '') . ' class="' . e($classes) . '"></i>';
    }
}
