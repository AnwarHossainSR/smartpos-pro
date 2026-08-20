<?php

namespace App\Services\Media;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Strips the executable parts out of an uploaded SVG.
 *
 * SVG is the one image format that is also a document: it can carry <script>, event
 * handler attributes, external references, and XML entities. Served from the media
 * bucket that is a stored-XSS primitive against whatever origin the bucket is bound
 * to — and a custom domain on R2 is often a subdomain of the app's own.
 *
 * The approach is an allowlist on elements and attributes rather than a blocklist of
 * known-bad tags: a blocklist has to enumerate every vector, an allowlist only has to
 * enumerate what a logo legitimately needs.
 */
class SvgSanitizer
{
    /** Elements a vector logo can plausibly be built from. */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata', 'symbol', 'use', 'switch',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern',
        'clipPath', 'mask', 'marker', 'style',
        'filter', 'feGaussianBlur', 'feOffset', 'feBlend', 'feColorMatrix',
        'feComposite', 'feFlood', 'feMerge', 'feMergeNode', 'feMorphology',
    ];

    /**
     * Attributes that survive. Presentation attributes are numerous but inert; the
     * ones deliberately absent are `on*` (handlers), `href`/`xlink:href` pointing
     * off-document, and anything that can name an external resource.
     */
    private const ALLOWED_ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewBox', 'xmlns', 'xmlns:xlink',
        'version', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'fill', 'fill-opacity',
        'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit',
        'opacity', 'offset', 'stop-color', 'stop-opacity', 'gradientUnits',
        'gradientTransform', 'spreadMethod', 'patternUnits', 'clip-path', 'clip-rule',
        'mask', 'filter', 'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing', 'preserveAspectRatio',
        'stdDeviation', 'in', 'in2', 'result', 'mode', 'type', 'values', 'operator',
    ];

    public static function clean(string $svg): string
    {
        // Entity declarations are the billion-laughs / XXE surface and have no place
        // in a logo, so the doctype goes before the parser ever sees it.
        $svg = preg_replace('/<!DOCTYPE[^>]*(\[[^\]]*\])?[^>]*>/is', '', $svg) ?? $svg;

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        // LIBXML_NONET blocks network fetches during parsing; NOENT is deliberately
        // NOT set, so remaining entities stay unexpanded.
        $loaded = @$document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if (! $loaded || ! $document->documentElement) {
            // Unparseable input is not something to guess at — hand back an empty
            // but valid SVG so the caller still has an image to store.
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>';
        }

        self::scrub($document->documentElement);

        // Processing instructions can carry an xml-stylesheet reference.
        $xpath = new DOMXPath($document);
        foreach (iterator_to_array($xpath->query('//processing-instruction()') ?: []) as $pi) {
            $pi->parentNode?->removeChild($pi);
        }

        return (string) $document->saveXML($document->documentElement);
    }

    private static function scrub(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            return;
        }

        // Iterated over a materialised copy: removing a child mutates the live
        // NodeList and would make a straight foreach skip every other element.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement && ! in_array($child->localName, self::ALLOWED_ELEMENTS, true)) {
                $node->removeChild($child);

                continue;
            }

            // <style> is allowed because most exported logos class their paths, but
            // its text is CSS, and CSS can fetch (`@import`, `url()`).
            if ($child instanceof DOMElement && $child->localName === 'style') {
                $child->textContent = preg_replace(
                    '/@import[^;]*;|url\s*\([^)]*\)|expression\s*\([^)]*\)/i',
                    '',
                    $child->textContent
                ) ?? '';

                continue;
            }

            self::scrub($child);
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $name = $attribute->nodeName;

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $node->removeAttribute($name);

                continue;
            }

            // `style` is allowed but can still smuggle a url() fetch or, on older
            // engines, an expression().
            if ($name === 'style' && preg_match('/url\s*\(|expression\s*\(|@import/i', $attribute->nodeValue ?? '')) {
                $node->removeAttribute($name);
            }
        }
    }
}
