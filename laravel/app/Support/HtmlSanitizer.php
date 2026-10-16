<?php

namespace App\Support;

/**
 * Giữ HTML bài viết/mô tả sản phẩm, bỏ script và thuộc tính sự kiện.
 * Dùng lúc hiển thị và lúc lưu, không sửa hàng loạt dữ liệu cũ.
 */
final class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'span', 'div',
        'blockquote', 'hr',
    ];

    /** @var list<string> */
    private const DROP_WITH_CHILDREN = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'base', 'form', 'svg', 'math', 'noscript', 'textarea', 'button',
        'input', 'select', 'option',
    ];

    public static function clean(?string $html): string
    {
        $html = str_replace("\0", '', (string) $html);
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="html-sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('html-sanitizer-root');
        if (! $root instanceof \DOMElement) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function walk(\DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->tagName);
            if (in_array($name, self::DROP_WITH_CHILDREN, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (! in_array($name, self::ALLOWED, true)) {
                self::unwrap($child);
                continue;
            }

            self::cleanAttributes($child);
            self::walk($child);
        }
    }

    private static function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        self::walk($element);
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function cleanAttributes(\DOMElement $element): void
    {
        $allowed = match (strtolower($element->tagName)) {
            'a' => ['href', 'title'],
            'img' => ['src', 'alt', 'title'],
            default => [],
        };

        $remove = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
                continue;
            }
            if (($name === 'href' || $name === 'src') && ! self::safeUrl($attribute->value, $name === 'src')) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    private static function safeUrl(string $value, bool $image): bool
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x1f]/', $value)) {
            return false;
        }

        $lower = strtolower(preg_replace('/\s+/', '', $value) ?? $value);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:') || str_starts_with($lower, 'data:')) {
            return false;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#') || str_starts_with($value, 'upload/')) {
            return true;
        }

        if (preg_match('#^https?://#i', $value)) {
            return true;
        }

        if (! $image && preg_match('#^(mailto:|tel:)#i', $value)) {
            return true;
        }

        return ! str_contains($value, ':');
    }
}
