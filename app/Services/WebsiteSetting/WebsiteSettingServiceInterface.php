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
}
