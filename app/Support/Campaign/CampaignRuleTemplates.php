<?php

namespace App\Support\Campaign;

use App\Enums\CampaignPlacement;
use App\Enums\CampaignTriggerType;

/**
 * Operator-friendly placement and trigger JSON templates for campaigns.
 */
class CampaignRuleTemplates
{
    /**
     * @return list<array{key: string, label: string, meaning: string, when_to_use: string, rules: array<string, mixed>}>
     */
    public function placementTemplates(): array
    {
        return [
            [
                'key' => 'global',
                'label' => 'Everywhere (global)',
                'meaning' => 'Eligible on all supported pages.',
                'when_to_use' => 'Site-wide welcome or announcement campaigns.',
                'rules' => [
                    'placements' => [CampaignPlacement::Global->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'home',
                'label' => 'Homepage only',
                'meaning' => 'Shows on the customer home surface.',
                'when_to_use' => 'Homepage banners and welcome popups.',
                'rules' => [
                    'placements' => [CampaignPlacement::Home->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'menu',
                'label' => 'Menu',
                'meaning' => 'Shows while browsing the menu.',
                'when_to_use' => 'Menu discovery and category highlights.',
                'rules' => [
                    'placements' => [CampaignPlacement::Menu->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'cart',
                'label' => 'Cart',
                'meaning' => 'Shows on the cart page.',
                'when_to_use' => 'Cart upsells and missing-item reminders.',
                'rules' => [
                    'placements' => [CampaignPlacement::Cart->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'checkout',
                'label' => 'Checkout',
                'meaning' => 'Shows during checkout.',
                'when_to_use' => 'Checkout reassurance or promo reminders.',
                'rules' => [
                    'placements' => [CampaignPlacement::Checkout->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'order_success',
                'label' => 'Order success',
                'meaning' => 'Shows after a successful order.',
                'when_to_use' => 'Referral or loyalty earn reminders.',
                'rules' => [
                    'placements' => [CampaignPlacement::OrderSuccess->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
            [
                'key' => 'home_and_menu',
                'label' => 'Home + Menu',
                'meaning' => 'Eligible on both home and menu placements.',
                'when_to_use' => 'Broader discovery without cart/checkout noise.',
                'rules' => [
                    'placements' => [
                        CampaignPlacement::Home->value,
                        CampaignPlacement::Menu->value,
                    ],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, meaning: string, when_to_use: string, rules: array<string, mixed>}>
     */
    public function triggerTemplates(): array
    {
        return [
            [
                'key' => 'immediate',
                'label' => 'Show immediately',
                'meaning' => 'Appears as soon as the surface loads.',
                'when_to_use' => 'Important announcements and welcome popups.',
                'rules' => [
                    'type' => CampaignTriggerType::Immediate->value,
                    'delay_ms' => null,
                    'scroll_percent' => null,
                    'product_view_count' => null,
                ],
            ],
            [
                'key' => 'delay_2s',
                'label' => 'After 2 seconds',
                'meaning' => 'Waits 2000 ms before showing.',
                'when_to_use' => 'Softer popups that should not interrupt first paint.',
                'rules' => [
                    'type' => CampaignTriggerType::Delay->value,
                    'delay_ms' => 2000,
                    'scroll_percent' => null,
                    'product_view_count' => null,
                ],
            ],
            [
                'key' => 'scroll_50',
                'label' => 'After scrolling 50%',
                'meaning' => 'Shows once the visitor scrolls halfway.',
                'when_to_use' => 'Engagement-based banners on long pages.',
                'rules' => [
                    'type' => CampaignTriggerType::Scroll->value,
                    'delay_ms' => null,
                    'scroll_percent' => 50,
                    'product_view_count' => null,
                ],
            ],
            [
                'key' => 'product_views_3',
                'label' => 'After 3 product views',
                'meaning' => 'Shows after the visitor views 3 products.',
                'when_to_use' => 'Repeat-interest / browsing intensity campaigns.',
                'rules' => [
                    'type' => CampaignTriggerType::ProductViews->value,
                    'delay_ms' => null,
                    'scroll_percent' => null,
                    'product_view_count' => 3,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function keyedPlacementTemplates(): array
    {
        return $this->keyBy($this->placementTemplates());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function keyedTriggerTemplates(): array
    {
        return $this->keyBy($this->triggerTemplates());
    }

    /**
     * @param  list<array{key: string, label: string, meaning: string, when_to_use: string, rules: array<string, mixed>}>  $templates
     * @return array<string, array<string, mixed>>
     */
    protected function keyBy(array $templates): array
    {
        $keyed = [];

        foreach ($templates as $template) {
            $keyed[$template['key']] = $template;
        }

        return $keyed;
    }
}
