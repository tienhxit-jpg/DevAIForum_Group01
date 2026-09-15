<?php

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

final class Sanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'a', 'img', 'h2', 'h3', 'h4', 'table', 'thead',
        'tbody', 'tr', 'th', 'td', 'hr', 'span',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'code' => ['class'],
        'span' => ['class'],
    ];

    public static function richText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="devai-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('devai-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                self::cleanChildren($node);
                while ($node->firstChild !== null) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);
                continue;
            }

            $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                if (!in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                    $node->removeAttribute($attribute->name);
                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && !self::safeUrl($value, $tag === 'img')) {
                    $node->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
            self::cleanChildren($node);
        }
    }

    private static function safeUrl(string $url, bool $allowRelative): bool
    {
        if ($allowRelative && (str_starts_with($url, '/') || str_starts_with($url, './'))) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}
