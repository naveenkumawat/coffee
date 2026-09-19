<?php

namespace App\Rules;

use App\Support\BrandLogoSvgSanitizer;
use App\Support\PublicMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class BrandLogoUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Primary logo must be SVG, PNG, JPG, JPEG, or WebP.');

            return;
        }

        $maxBytes = PublicMedia::brandLogoMaxKilobytes() * 1024;

        if ($value->getSize() > $maxBytes) {
            $fail('Primary logo must not be larger than '.PublicMedia::brandLogoMaxMegabytesLabel().' MB.');

            return;
        }

        $extension = strtolower((string) $value->getClientOriginalExtension());
        $guessed = strtolower((string) $value->extension());
        $allowed = PublicMedia::brandLogoAllowedExtensions();

        if (! in_array($extension, $allowed, true) && ! in_array($guessed, $allowed, true)) {
            $fail('Primary logo must be SVG, PNG, JPG, JPEG, or WebP.');

            return;
        }

        $effective = in_array($extension, $allowed, true) ? $extension : $guessed;
        $mime = strtolower((string) ($value->getMimeType() ?: ''));

        $mimeOk = match ($effective) {
            'svg' => $this->isSvgMime($mime) && $this->looksLikeSvg($value),
            'png' => $mime === 'image/png',
            'jpg', 'jpeg' => $mime === 'image/jpeg',
            'webp' => in_array($mime, ['image/webp', 'image/x-webp'], true),
            default => false,
        };

        if (! $mimeOk) {
            $fail('Primary logo must be SVG, PNG, JPG, JPEG, or WebP.');

            return;
        }

        if ($effective !== 'svg') {
            return;
        }

        $path = $value->getRealPath();
        $contents = is_string($path) && $path !== '' ? (string) file_get_contents($path) : '';

        if ((new BrandLogoSvgSanitizer)->clean($contents) === null) {
            $fail('Primary logo must be SVG, PNG, JPG, JPEG, or WebP.');
        }
    }

    private function isSvgMime(string $mime): bool
    {
        return in_array($mime, [
            'image/svg+xml',
            'image/svg',
            'text/xml',
            'application/xml',
            'text/plain',
            'text/html',
        ], true);
    }

    private function looksLikeSvg(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return false;
        }

        $head = strtolower((string) file_get_contents($path, false, null, 0, 2048));

        return str_contains($head, '<svg');
    }
}
