<?php

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            WebsiteSettingKey::BusinessTimezone->value => 'Asia/Kolkata',
            WebsiteSettingKey::OrderingManualClosed->value => '0',
            WebsiteSettingKey::OrderingManualClosedUntil->value => null,
            WebsiteSettingKey::OrderingManualClosedMessage->value => null,
        ];

        foreach ([
            WebsiteSettingKey::BusinessTimezone,
            WebsiteSettingKey::OrderingManualClosed,
            WebsiteSettingKey::OrderingManualClosedUntil,
            WebsiteSettingKey::OrderingManualClosedMessage,
        ] as $key) {
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
            ->whereIn('key', [
                WebsiteSettingKey::BusinessTimezone->value,
                WebsiteSettingKey::OrderingManualClosed->value,
                WebsiteSettingKey::OrderingManualClosedUntil->value,
                WebsiteSettingKey::OrderingManualClosedMessage->value,
            ])
            ->delete();
    }
};
