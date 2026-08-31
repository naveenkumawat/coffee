<?php

use App\Enums\WebsiteSettingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $key = WebsiteSettingKey::FulfilmentDineInEnabled->value;

        $exists = DB::table('website_settings')->where('key', $key)->exists();

        if (! $exists) {
            DB::table('website_settings')->insert([
                'key' => $key,
                'value' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('website_settings')
            ->where('key', WebsiteSettingKey::FulfilmentDineInEnabled->value)
            ->delete();
    }
};
