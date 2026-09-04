<?php

namespace App\Enums;

enum HomeSectionSourceType: string
{
    case Curated = 'curated';
    case Recommendation = 'recommendation';
    case BuyAgain = 'buy_again';
    case Favourite = 'favourite';
    case RepeatedInterest = 'repeated_interest';
    case Affinity = 'affinity';
    case Trending = 'trending';
    case Popular = 'popular';
    case NewArrival = 'new_arrival';
    case Featured = 'featured';
    case Bestseller = 'bestseller';
    case Category = 'category';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::Curated => 'Curated products',
            self::Recommendation => 'Recommendation rail (full)',
            self::BuyAgain => 'Buy again',
            self::Favourite => 'Favourites',
            self::RepeatedInterest => 'Repeated interest',
            self::Affinity => 'Affinity',
            self::Trending => 'Trending',
            self::Popular => 'Popular',
            self::NewArrival => 'New arrivals',
            self::Featured => 'Featured',
            self::Bestseller => 'Bestsellers',
            self::Category => 'Category products',
            self::Tag => 'Tagged products',
        };
    }

    public function isRecommendationBacked(): bool
    {
        return in_array($this, [
            self::Recommendation,
            self::BuyAgain,
            self::Favourite,
            self::RepeatedInterest,
            self::Affinity,
            self::Trending,
            self::Popular,
            self::NewArrival,
            self::Featured,
            self::Bestseller,
        ], true);
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
