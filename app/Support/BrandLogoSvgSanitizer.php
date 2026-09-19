<?php

namespace App\Support;

use enshrined\svgSanitize\Sanitizer;

/**
 * Sanitizes primary-logo SVG before it is stored on the public disk.
 */
class BrandLogoSvgSanitizer
{
    public function clean(string $dirty): ?string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->setAllowHugeFiles(true);

        $clean = $sanitizer->sanitize($dirty);

        if (! is_string($clean) || trim($clean) === '') {
            return null;
        }

        if (! str_contains(strtolower($clean), '<svg')) {
            return null;
        }

        return $clean;
    }
}
