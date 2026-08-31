<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if (! filled($digits)) {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Meta Cloud API destination digits (country code + national number, no '+').
     * Uses existing normalize() first; prepends 91 only for 10-digit Indian mobiles.
     */
    public static function toWhatsappDestination(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $rawDigits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($rawDigits === '') {
            return null;
        }

        // Already international (e.g. 9198… or other E.164 without '+').
        if (strlen($rawDigits) >= 11 && strlen($rawDigits) <= 15 && ! str_starts_with($rawDigits, '0')) {
            return $rawDigits;
        }

        $normalized = self::normalize($value);

        if ($normalized === null) {
            return null;
        }

        if (strlen($normalized) === 10 && preg_match('/^[6-9]\d{9}$/', $normalized) === 1) {
            return '91'.$normalized;
        }

        if (strlen($normalized) >= 11 && strlen($normalized) <= 15) {
            return $normalized;
        }

        return null;
    }

    public static function looksLikePhone(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '' || str_contains($trimmed, '@')) {
            return false;
        }

        return (bool) preg_match('/\d/', $trimmed);
    }

    public static function looksLikeEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }
}
