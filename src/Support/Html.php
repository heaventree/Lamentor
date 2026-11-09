<?php

namespace Lamentor\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist HTML sanitizer (the role wp_kses_post plays in Elementor).
 * Disallowed elements are unwrapped (script/style/etc. are dropped with their
 * content); attributes are limited to a safe set; event handlers and
 * script-ish URLs are removed.
 */
final class Html
{
    public const INLINE = ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'ins', 'br', 'span', 'sub', 'sup', 'small', 'mark', 'code'];

    public const RICH = [
        ...self::INLINE,
        'p', 'div', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'hr',
        'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript'];

    private const ATTRIBUTES = ['href', 'src', 'alt', 'title', 'class', 'id', 'style', 'target', 'rel', 'width', 'height', 'colspan', 'rowspan', 'dir', 'lang'];

    /** @param list<string> $allowedTags */
    public static function sanitize(string $html, array $allowedTags = self::INLINE): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="lamentor-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('lamentor-root');
        if (! $root) {
            return '';
        }

        self::clean($root, $allowedTags);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function clean(DOMNode $parent, array $allowed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::clean($node, $allowed);

            if (! in_array($tag, $allowed, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            self::cleanAttributes($node);
        }
    }

    private static function cleanAttributes(DOMElement $node): void
    {
        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            $unsafe = ! in_array($name, self::ATTRIBUTES, true)
                || (in_array($name, ['href', 'src'], true) && ! self::safeUrl($value))
                || ($name === 'style' && preg_match('/expression\s*\(|url\s*\(|javascript:|behavior:|-moz-binding/i', $value));

            if ($unsafe) {
                $node->removeAttribute($attr->name);
            }
        }

        if ($node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', trim($node->getAttribute('rel') . ' noopener'));
        }
    }

    private static function safeUrl(string $url): bool
    {
        // Browsers ignore whitespace/control characters inside the scheme.
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $url));

        return ! preg_match('/^(javascript|vbscript|data):/', $normalized);
    }
}
