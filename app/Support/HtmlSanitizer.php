<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for editor (CKEditor) content.
 *
 * Anything not explicitly allowed is removed: dangerous elements are dropped
 * with their content, unknown elements are unwrapped, unknown attributes
 * (including every on* handler and style) are stripped, and URLs are limited
 * to safe schemes.
 */
final class HtmlSanitizer
{
    /** tag => allowed attributes */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [], 'ul' => [], 'ol' => ['start'], 'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    private const GLOBAL_ATTRIBUTES = ['class'];

    /** Removed together with everything inside them. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'option',
        'button', 'input', 'link', 'meta', 'base', 'title', 'head', 'audio', 'video',
        'source', 'canvas',
    ];

    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const IMAGE_SCHEMES = ['http', 'https'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><html><body><div>'.$html.'</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementsByTagName('div')->item(0);

        if (! $root) {
            return '';
        }

        self::sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                // comments, processing instructions, CDATA ...
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeChildren($node);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Unknown element: keep its (already sanitized) children.
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeAttributes($node, $tag);
        }
    }

    private static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::ALLOWED[$tag], self::GLOBAL_ATTRIBUTES);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'href' && ! self::isSafeUrl($attr->nodeValue, self::LINK_SCHEMES, true)) {
                $el->removeAttribute($attr->nodeName);
            } elseif ($name === 'src' && ! self::isSafeUrl($attr->nodeValue, self::IMAGE_SCHEMES, false)) {
                $el->removeAttribute($attr->nodeName);
            } elseif ($name === 'target' && $attr->nodeValue !== '_blank') {
                $el->removeAttribute($attr->nodeName);
            } elseif (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'start'], true)
                && ! ctype_digit((string) $attr->nodeValue)) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        if ($tag === 'img' && ! $el->hasAttribute('src')) {
            $el->parentNode?->removeChild($el);

            return;
        }

        if ($tag === 'a') {
            if ($el->getAttribute('target') === '_blank') {
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            } else {
                $el->removeAttribute('rel');
            }
        }
    }

    private static function isSafeUrl(string $url, array $schemes, bool $allowFragment): bool
    {
        // Browsers ignore whitespace/control chars inside the scheme ("java\nscript:").
        $clean = preg_replace('/[\x00-\x20\x7f]+/u', '', $url) ?? '';

        if ($clean === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $clean, $m)) {
            return in_array(strtolower($m[1]), $schemes, true);
        }

        if ($allowFragment && $clean[0] === '#') {
            return true;
        }

        // Relative path ("/uploads/x.jpg", "foo/bar"); protocol-relative "//host" is allowed too.
        return true;
    }
}
