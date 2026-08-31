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

        foreach (WebsiteSettingKey::ordered() as $key) {
            if (in_array($key->value, $existing, true)) {
                continue;
            }

            DB::table('website_settings')->insert([
                'key' => $key->value,
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->whereIn('key', [
                WebsiteSettingKey::PaymentPhone->value,
                WebsiteSettingKey::PaymentQrImagePath->value,
                WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value,
            ])
            ->delete();
    }
};
