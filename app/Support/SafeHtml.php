<?php

namespace App\Support;

/**
 * Allowlisted HTML for CMS page bodies. Strips scripts, styles, and event handlers.
 */
final class SafeHtml
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a',
        'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote',
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $value = trim(str_replace("\0", '', $html));

        if ($value === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $wrapped = '<?xml encoding="UTF-8"><div id="safe-html-root">'.$value.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('safe-html-root');

        if (! $root instanceof \DOMElement) {
            return null;
        }

        self::scrubNode($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        $clean = trim($clean);

        return $clean === '' ? null : $clean;
    }

    private static function scrubNode(\DOMNode $node): void
    {
        $toDrop = [];
        $toUnwrap = [];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
                    $toDrop[] = $child;

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::scrubNode($child);
                    $toUnwrap[] = $child;

                    continue;
                }

                self::scrubAttributes($child, $tag);
                self::scrubNode($child);
            }
        }

        foreach ($toDrop as $child) {
            $node->removeChild($child);
        }

        foreach ($toUnwrap as $child) {
            $fragment = $node->ownerDocument?->createDocumentFragment();

            if ($fragment instanceof \DOMDocumentFragment) {
                while ($child->firstChild) {
                    $fragment->appendChild($child->firstChild);
                }
                $node->replaceChild($fragment, $child);
            } else {
                $node->removeChild($child);
            }
        }
    }

    private static function scrubAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = $tag === 'a' ? ['href', 'title', 'rel', 'target'] : [];
        $remove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag !== 'a') {
            return;
        }

        $href = trim($element->getAttribute('href'));

        if ($href === '' || preg_match('#^(javascript|data|vbscript):#i', $href) === 1) {
            $element->removeAttribute('href');

            return;
        }

        if (! preg_match('#^(https?:|mailto:|tel:|/|#)#i', $href)) {
            $element->removeAttribute('href');
        }

        $element->setAttribute('rel', 'noopener noreferrer');
    }
}
