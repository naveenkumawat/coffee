<?php

use App\Enums\WebsiteSettingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = WebsiteSettingKey::CustomerCartEnabled;

        if (DB::table('website_settings')->where('key', $key->value)->exists()) {
            return;
        }

        $now = now();

        DB::table('website_settings')->insert([
            'key' => $key->value,
            'section' => $key->section(),
            'value_type' => $key->valueType(),
            'value' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->where('key', WebsiteSettingKey::CustomerCartEnabled->value)
            ->delete();
    }
};
