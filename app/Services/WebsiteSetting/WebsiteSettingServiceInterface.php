<?php

namespace App\Services\WebsiteSetting;

interface WebsiteSettingServiceInterface
{
    /**
     * @return array<string, string|null>
     */
    public function valuesForAdmin(): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): void;

    /**
     * Customer-safe website content payload.
     *
     * @return array{
     *     hero: array{title: ?string, subtitle: ?string, image_path: ?string},
     *     business: array{
     *         name: ?string,
     *         about_short: ?string,
     *         phone: ?string,
     *         whatsapp_number: ?string,
     *         email: ?string,
     *         address: ?string,
     *         opening_hours: ?string
     *     },
     *     payment: array{
     *         display_name: ?string,
     *         instructions: ?string,
     *         upi_id: ?string,
     *         phone: ?string,
     *         qr_image_path: ?string,
     *         whatsapp_number: ?string
     *     },
     *     fulfilment: array{delivery_disclaimer: ?string, dine_in_enabled: bool},
     *     pages: array{
     *         about: ?string,
     *         contact: ?string,
     *         faq: ?string,
     *         terms: ?string,
     *         privacy: ?string
     *     },
     *     social_links: list<array{label: string, icon_key: string, url: string, sort_order: int}>
     * }
     */
    public function customerContent(): array;

    /**
     * Resolved payment display info. Non-empty DB settings override config/env.
     *
     * @return array{
     *     display_name: ?string,
     *     instructions: ?string,
     *     upi_id: ?string,
     *     phone: ?string,
     *     qr_image_path: ?string,
     *     whatsapp_number: ?string
     * }
     */
    public function paymentInstructions(): array;

    public function deliveryDisclaimer(): ?string;

    public function dineInEnabled(): bool;

    /**
     * Dining / table-service feature toggle (same setting as legacy fulfilment_dine_in_enabled).
     */
    public function diningEnabled(): bool;

    /**
     * @return array{
     *     enabled: bool,
     *     max_open_unpaid_orders: int,
     *     max_orders_per_hour: int,
     *     checkout_attempts_per_10_minutes: int,
     *     payment_proof_attempts_per_15_minutes: int,
     *     duplicate_order_window_minutes: int
     * }
     */
    public function orderSecurityConfig(): array;

    /**
     * Live tax/GST configuration from Website Settings.
     *
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     percent: string,
     *     inclusive: bool,
     *     gstin: ?string,
     *     legal_business_name: ?string
     * }
     */
    public function taxConfig(): array;

    /**
     * @return array{
     *     enabled: bool,
     *     reward_type: string,
     *     reward_product_id: ?int,
     *     reward_variant_id: ?int,
     *     reward_quantity: int,
     *     coupon_discount_type: string,
     *     coupon_discount_value: string,
     *     coupon_max_discount: ?string,
     *     coupon_minimum_subtotal: ?string,
     *     minimum_qualifying_order_amount: ?string,
     *     reward_redemption_duration_days: int,
     *     max_rewards_per_customer_month: ?int
     * }
     */
    public function referralConfig(): array;
}
