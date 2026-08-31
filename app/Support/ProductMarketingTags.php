<?php

namespace App\Support;

/**
 * Known marketing tag slugs that stay mirrored onto product storefront flags
 * for catalog filters and home rails (tags remain the assignment source of truth).
 */
final class ProductMarketingTags
{
    public const NEW = 'new';

    public const TOP_SELLER = 'top-seller';

    public const FEATURED = 'featured';

    /**
     * @return array<string, string>
     */
    public static function flagMap(): array
    {
        return [
            self::NEW => 'is_new',
            self::TOP_SELLER => 'is_bestseller',
            self::FEATURED => 'is_featured',
        ];
    }
}
