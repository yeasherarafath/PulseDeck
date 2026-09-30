<?php

namespace App\Services\Status;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist SVG sanitizer for uploaded logos, which are served raw.
 *
 * Documents with a DOCTYPE/entities are rejected (XXE), unknown elements are
 * dropped with their children, and only safe attributes survive. Links may
 * only point at in-document fragments (#id).
 */
class SvgSanitizer
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';

    private const XLINK_NS = 'http://www.w3.org/1999/xlink';

    /** @var list<string> */
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line',
        'polyline', 'polygon', 'text', 'tspan', 'lineargradient', 'radialgradient', 'stop',
        'clippath', 'mask', 'pattern', 'symbol', 'use',
    ];

    /** @var list<string> */
    private const ATTRIBUTES = [
        'id', 'class', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height',
        'd', 'points', 'transform', 'viewbox', 'preserveaspectratio', 'version', 'fill', 'fill-opacity',
        'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit', 'opacity', 'offset', 'stop-color',
        'stop-opacity', 'gradientunits', 'gradienttransform', 'fx', 'fy', 'patternunits',
        'patterntransform', 'clip-path', 'clip-rule', 'mask', 'font-family', 'font-size', 'font-weight',
        'font-style', 'text-anchor', 'dominant-baseline', 'dx', 'dy', 'style', 'href', 'xmlns', 'display',
        'visibility', 'spreadmethod', 'maskunits', 'maskcontentunits', 'clippathunits', 'role', 'aria-label',
    ];

    /** Returns clean SVG XML, or null when the input cannot be made safe. */
    public static function sanitize(string $xml): ?string
    {
        if (trim($xml) === '' || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument;

            if (! $dom->loadXML($xml, LIBXML_NONET) || ! $dom->documentElement) {
                return null;
            }

            $root = $dom->documentElement;

            if (strtolower($root->localName) !== 'svg') {
                return null;
            }

            self::cleanChildren($root);
            self::cleanAttributes($root);

            return $dom->saveXML($root) ?: null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $namespace = $child->namespaceURI;

                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)
                    || ($namespace !== null && $namespace !== self::SVG_NS)) {
                    $parent->removeChild($child);

                    continue;
                }

                self::cleanAttributes($child);
                self::cleanChildren($child);
            } elseif ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                $parent->removeChild($child); // comments, processing instructions
            }
        }
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->localName);
            $namespace = $attribute->namespaceURI;

            $allowedNamespace = $namespace === null
                || ($namespace === self::XLINK_NS && $name === 'href')
                || $namespace === 'http://www.w3.org/2000/xmlns/';

            if (! $allowedNamespace || (! in_array($name, self::ATTRIBUTES, true) && $namespace !== 'http://www.w3.org/2000/xmlns/')) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            // Browsers ignore control characters/whitespace inside URLs and schemes.
            $value = preg_replace('/[\x00-\x20\x7F]+/u', '', (string) $attribute->value) ?? '';

            if ($name === 'href' && ! str_starts_with($value, '#')) {
                $element->removeAttributeNode($attribute);
            } elseif (preg_match('/javascript:|data:|vbscript:|expression\(|@import|url\((?!["\']?#)/i', $value)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
