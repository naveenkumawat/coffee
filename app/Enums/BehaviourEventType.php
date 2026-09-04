<?php

namespace App\Enums;

enum BehaviourEventType: string
{
    // Client interaction events (PWA may submit)
    case ProductViewed = 'product_viewed';
    case CategoryViewed = 'category_viewed';
    case SearchPerformed = 'search_performed';
    case ProductCustomized = 'product_customized';
    case CartItemAdded = 'cart_item_added';
    case CartItemRemoved = 'cart_item_removed';
    case CheckoutStarted = 'checkout_started';
    case FavouriteAdded = 'favourite_added';
    case FavouriteRemoved = 'favourite_removed';

    // Server business events (Laravel authoritative; reject from clients)
    case OrderCompleted = 'order_completed';
    case RecommendationConverted = 'recommendation_converted';
    case CampaignConverted = 'campaign_converted';

    // Recommendation feedback (P2.3)
    case RecommendationImpression = 'recommendation_impression';
    case RecommendationClicked = 'recommendation_clicked';

    // Campaign feedback (P2.4)
    case CampaignImpression = 'campaign_impression';
    case CampaignClicked = 'campaign_clicked';
    case CampaignDismissed = 'campaign_dismissed';

    /**
     * @return list<self>
     */
    public static function clientIngestible(): array
    {
        return [
            self::ProductViewed,
            self::CategoryViewed,
            self::SearchPerformed,
            self::ProductCustomized,
            self::CartItemAdded,
            self::CartItemRemoved,
            self::CheckoutStarted,
            self::FavouriteAdded,
            self::FavouriteRemoved,
            self::RecommendationImpression,
            self::RecommendationClicked,
            self::CampaignImpression,
            self::CampaignClicked,
            self::CampaignDismissed,
        ];
    }

    /**
     * @return list<string>
     */
    public static function clientIngestibleValues(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::clientIngestible(),
        );
    }

    public function isClientIngestible(): bool
    {
        return in_array($this, self::clientIngestible(), true);
    }

    public function isServerAuthoritative(): bool
    {
        return in_array($this, [
            self::OrderCompleted,
            self::RecommendationConverted,
            self::CampaignConverted,
        ], true);
    }
}
