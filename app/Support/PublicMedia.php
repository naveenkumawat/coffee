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

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        if (str_starts_with($value, 'storage/')) {
            return url('/'.$value);
        }

        $generated = Storage::disk(self::disk())->url($value);

        if (preg_match('#^https?://#i', $generated) === 1) {
            return $generated;
        }

        return url($generated);
    }

    /**
     * Store an uploaded image under the given directory on the public disk.
     * Returns a disk-relative path suitable for DB storage.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, self::allowedExtensions(), true)) {
            $guessed = strtolower((string) $file->extension());
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

        foreach ([self::DIRECTORY_PRODUCTS, self::DIRECTORY_CATEGORIES, self::DIRECTORY_WEBSITE] as $directory) {
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
