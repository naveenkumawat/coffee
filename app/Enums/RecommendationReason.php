<?php

namespace App\Enums;

enum RecommendationReason: string
{
    case BuyAgain = 'buy_again';
    case Favourite = 'favourite';
    case BecauseYouViewed = 'because_you_viewed';
    case BasedOnYourInterests = 'based_on_your_interests';
    case SimilarProduct = 'similar_product';
    case FrequentlyBoughtTogether = 'frequently_bought_together';
    case Trending = 'trending';
    case Popular = 'popular';
    case Bestseller = 'bestseller';
    case NewArrival = 'new_arrival';
    case Featured = 'featured';
    case CompleteYourOrder = 'complete_your_order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
