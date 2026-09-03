<?php

namespace App\Enums;

enum RecommendationContext: string
{
    case Home = 'home';
    case ProductDetail = 'product_detail';
    case Menu = 'menu';
    case Cart = 'cart';
    case PostOrder = 'post_order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
