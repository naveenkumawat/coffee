<?php

use App\Enums\BrandDisplayMode;
use App\Enums\WebsiteSettingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $key = WebsiteSettingKey::BrandDisplayMode;

        $exists = DB::table('website_settings')->where('key', $key->value)->exists();

        if ($exists) {
            DB::table('website_settings')
                ->where('key', $key->value)
                ->where(function ($query): void {
                    $query->whereNull('value')->orWhere('value', '');
                })
                ->update([
                    'value' => BrandDisplayMode::LogoNameTagline->value,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('website_settings')->insert([
            'key' => $key->value,
            'section' => $key->section(),
            'value_type' => $key->valueType(),
            'value' => BrandDisplayMode::LogoNameTagline->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->where('key', WebsiteSettingKey::BrandDisplayMode->value)
            ->delete();
    }
};
