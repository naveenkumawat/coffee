<?php

namespace App\Enums;

enum CampaignPlacement: string
{
    case Global = 'global';
    case Home = 'home';
    case Menu = 'menu';
    case Category = 'category';
    case ProductDetail = 'product_detail';
    case Cart = 'cart';
    case Checkout = 'checkout';
    case OrderSuccess = 'order_success';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global (any page)',
            self::Home => 'Home',
            self::Menu => 'Menu',
            self::Category => 'Category',
            self::ProductDetail => 'Product detail',
            self::Cart => 'Cart',
            self::Checkout => 'Checkout',
            self::OrderSuccess => 'Order success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
