<?php

use App\Enums\WebsiteSettingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('website_settings')->pluck('key')->all();

        $taxKeys = [
            WebsiteSettingKey::TaxEnabled,
            WebsiteSettingKey::TaxLabel,
            WebsiteSettingKey::TaxPercent,
            WebsiteSettingKey::TaxInclusive,
            WebsiteSettingKey::TaxGstin,
            WebsiteSettingKey::TaxLegalBusinessName,
        ];

        foreach ($taxKeys as $key) {
            if (in_array($key->value, $existing, true)) {
                continue;
            }

            $default = match ($key) {
                WebsiteSettingKey::TaxEnabled, WebsiteSettingKey::TaxInclusive => '0',
                WebsiteSettingKey::TaxLabel => 'GST',
                WebsiteSettingKey::TaxPercent => '0.00',
                default => null,
            };

            DB::table('website_settings')->insert([
                'key' => $key->value,
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $default,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->whereIn('key', [
                WebsiteSettingKey::TaxEnabled->value,
                WebsiteSettingKey::TaxLabel->value,
                WebsiteSettingKey::TaxPercent->value,
                WebsiteSettingKey::TaxInclusive->value,
                WebsiteSettingKey::TaxGstin->value,
                WebsiteSettingKey::TaxLegalBusinessName->value,
            ])
            ->delete();
    }
};
