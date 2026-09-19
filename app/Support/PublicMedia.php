<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public-disk catalog / website imagery (products, categories, hero, payment QR).
 * Paths stored relative to the public disk root (e.g. products/uuid.webp).
 */
class PublicMedia
{
    public const DIRECTORY_PRODUCTS = 'products';

    public const DIRECTORY_CATEGORIES = 'categories';

    public const DIRECTORY_FLAVOURS = 'flavours';

    public const DIRECTORY_ADDONS = 'add-ons';

    public const DIRECTORY_WEBSITE = 'website';

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp'];
    }

    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

    public static function disk(): string
    {
        return (string) config('coffee.media.disk', 'public');
    }

    public static function maxKilobytes(): int
    {
        return max(1, (int) config('coffee.media.max_kilobytes', 512));
    }

    /**
     * @return list<string>
     */
    public static function brandLogoAllowedExtensions(): array
    {
        return ['svg', 'png', 'jpg', 'jpeg', 'webp'];
    }

    /**
     * @return list<string>
     */
    public static function brandLogoAllowedMimes(): array
    {
        return ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];
    }

    public static function brandLogoMaxKilobytes(): int
    {
        return max(1, (int) config('coffee.media.brand_logo_max_kilobytes', 5120));
    }

    public static function brandLogoMaxMegabytesLabel(): string
    {
        return (string) max(1, intdiv(self::brandLogoMaxKilobytes(), 1024));
    }

    /**
     * Validation rules for an optional uploaded image field.
     *
     * @return list<string>
     */
    public static function uploadRules(): array
    {
        return [
            'nullable',
            'file',
            'image',
            'mimes:'.implode(',', self::allowedExtensions()),
            'max:'.self::maxKilobytes(),
        ];
    }

    /**
     * Absolute public URL for API/PWA clients, or null when empty.
     * Accepts managed relative paths, site-relative paths, or absolute URLs.
     */
    public static function url(?string $path): ?string
    {
        $value = self::normalizePath($path);

        if ($value === null) {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        $relative = ltrim(str_replace('\\', '/', $value), '/');

        // url('storage/...') appends to APP_URL (including /coffee subdirectory).
        // url('/storage/...') is host-absolute and drops the subdirectory.
        if (str_starts_with($relative, 'storage/')) {
            return url($relative);
        }

        if (self::isManagedRelativePath($relative)) {
            return url('storage/'.$relative);
        }

        $generated = Storage::disk(self::disk())->url($relative);

        if (preg_match('#^https?://#i', $generated) === 1) {
            return $generated;
        }

        return url(ltrim((string) $generated, '/'));
    }

    /**
     * Store an uploaded image under the given directory on the public disk.
     * Returns a disk-relative path suitable for DB storage.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $guessed = strtolower((string) $file->extension());

        if ($extension === 'svg' || $guessed === 'svg') {
            throw ValidationException::withMessages([
                'image' => 'SVG is only accepted for the primary logo.',
            ]);
        }

        if (! in_array($extension, self::allowedExtensions(), true)) {
            $extension = in_array($guessed, self::allowedExtensions(), true) ? $guessed : 'jpg';
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::disk());

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'image' => 'Unable to store the image. Please try again.',
            ]);
        }

        return $path;
    }

    /**
     * Store a primary logo. Raster files use the generic PublicMedia pipeline;
     * SVG is sanitized, then written with a generated UUID filename.
     */
    public static function storeBrandLogo(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::brandLogoAllowedExtensions(), true)) {
            $guessed = strtolower((string) $file->extension());
            $extension = in_array($guessed, self::brandLogoAllowedExtensions(), true) ? $guessed : '';
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        if ($extension === 'svg') {
            $path = $file->getRealPath();
            $contents = is_string($path) && $path !== '' ? (string) file_get_contents($path) : '';
            $clean = (new BrandLogoSvgSanitizer)->clean($contents);

            if ($clean === null) {
                throw ValidationException::withMessages([
                    'brand_logo' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
                ]);
            }

            $filename = Str::uuid()->toString().'.svg';
            $relative = self::DIRECTORY_WEBSITE.'/'.$filename;
            $stored = Storage::disk(self::disk())->put($relative, $clean);

            if ($stored !== true) {
                throw ValidationException::withMessages([
                    'brand_logo' => 'Unable to store the image. Please try again.',
                ]);
            }

            return $relative;
        }

        if (! in_array($extension, self::allowedExtensions(), true)) {
            throw ValidationException::withMessages([
                'brand_logo' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
            ]);
        }

        return self::store($file, self::DIRECTORY_WEBSITE);
    }

    /**
     * Delete a previously stored managed public-disk path. Ignores absolute URLs
     * and non-managed paths so external/CDN references are left alone.
     */
    public static function deleteManaged(?string $path): void
    {
        $value = self::normalizePath($path);

        if ($value === null || ! self::isManagedRelativePath($value)) {
            return;
        }

        $disk = Storage::disk(self::disk());

        if ($disk->exists($value)) {
            $disk->delete($value);
        }
    }

    public static function isManagedRelativePath(string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        foreach ([self::DIRECTORY_PRODUCTS, self::DIRECTORY_CATEGORIES, self::DIRECTORY_FLAVOURS, self::DIRECTORY_ADDONS, self::DIRECTORY_WEBSITE] as $directory) {
            if (str_starts_with($normalized, $directory.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $value = trim($path);

        return $value === '' ? null : $value;
    }
}
