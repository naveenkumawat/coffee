<?php

namespace App\Support;

use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;

final class CustomerEmailBrand
{
    /**
     * @return array{
     *     business_name: string,
     *     slogan: string|null,
     *     phone: string|null,
     *     whatsapp: string|null,
     *     email: string|null,
     *     address: string|null,
     *     opening_hours: string|null,
     *     delivery_disclaimer: string|null
     * }
     */
    public static function snapshot(?WebsiteSettingServiceInterface $settings = null): array
    {
        $settings ??= app(WebsiteSettingServiceInterface::class);
        $content = $settings->customerContent();

        $businessName = trim((string) ($content['business']['name'] ?? '')) ?: (string) config('coffee.company.name', 'The88Coffees');
        $slogan = filled($content['hero']['subtitle'] ?? null) ? (string) $content['hero']['subtitle'] : null;

        return [
            'business_name' => $businessName,
            'slogan' => $slogan,
            'phone' => filled($content['business']['phone'] ?? null) ? (string) $content['business']['phone'] : null,
            'whatsapp' => filled($content['business']['whatsapp_number'] ?? null) ? (string) $content['business']['whatsapp_number'] : null,
            'email' => filled($content['business']['email'] ?? null) ? (string) $content['business']['email'] : null,
            'address' => filled($content['business']['address'] ?? null) ? (string) $content['business']['address'] : null,
            'opening_hours' => filled($content['business']['opening_hours'] ?? null) ? (string) $content['business']['opening_hours'] : null,
            'delivery_disclaimer' => filled($content['fulfilment']['delivery_disclaimer'] ?? null)
                ? (string) $content['fulfilment']['delivery_disclaimer']
                : null,
        ];
    }

    public static function firstName(?string $fullName): ?string
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return null;
        }

        return explode(' ', $fullName)[0] ?: null;
    }
}
