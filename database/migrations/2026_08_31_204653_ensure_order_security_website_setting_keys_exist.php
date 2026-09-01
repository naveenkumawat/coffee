<?php

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            WebsiteSettingKey::OrderSecurityEnabled->value => '1',
            WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders->value => '2',
            WebsiteSettingKey::OrderSecurityMaxOrdersPerHour->value => '5',
            WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes->value => '5',
            WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes->value => '5',
            WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes->value => '3',
        ];

        foreach (WebsiteSettingKey::cases() as $key) {
            if ($key->section() !== 'order_security') {
                continue;
            }

            WebsiteSetting::query()->firstOrCreate(
                ['key' => $key->value],
                [
                    'section' => $key->section(),
                    'value_type' => $key->valueType(),
                    'value' => $defaults[$key->value] ?? null,
                ],
            );
        }
    }

    public function down(): void
    {
        WebsiteSetting::query()
            ->whereIn('key', array_map(
                static fn (WebsiteSettingKey $key): string => $key->value,
                array_values(array_filter(
                    WebsiteSettingKey::cases(),
                    static fn (WebsiteSettingKey $key): bool => $key->section() === 'order_security',
                )),
            ))
            ->delete();
    }
};
