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

        foreach ([WebsiteSettingKey::BrandLogoPath, WebsiteSettingKey::BrandFaviconPath] as $key) {
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

        foreach ([WebsiteSettingKey::BusinessName, WebsiteSettingKey::HeroSubtitle] as $key) {
            DB::table('website_settings')
                ->where('key', $key->value)
                ->update([
                    'section' => $key->section(),
                    'value_type' => $key->valueType(),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->whereIn('key', [
                WebsiteSettingKey::BrandLogoPath->value,
                WebsiteSettingKey::BrandFaviconPath->value,
            ])
            ->delete();

        DB::table('website_settings')
            ->where('key', WebsiteSettingKey::BusinessName->value)
            ->update(['section' => 'business']);

        DB::table('website_settings')
            ->where('key', WebsiteSettingKey::HeroSubtitle->value)
            ->update(['section' => 'hero']);
    }
};
