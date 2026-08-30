<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('section', 32)->index();
            $table->string('value_type', 16)->default('string');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();

        $rows = [
            ['key' => 'hero_title', 'section' => 'hero', 'value_type' => 'string'],
            ['key' => 'hero_subtitle', 'section' => 'hero', 'value_type' => 'text'],
            ['key' => 'hero_image_path', 'section' => 'hero', 'value_type' => 'string'],
            ['key' => 'business_name', 'section' => 'business', 'value_type' => 'string'],
            ['key' => 'business_about_short', 'section' => 'business', 'value_type' => 'text'],
            ['key' => 'business_phone', 'section' => 'business', 'value_type' => 'string'],
            ['key' => 'business_whatsapp_number', 'section' => 'business', 'value_type' => 'string'],
            ['key' => 'business_email', 'section' => 'business', 'value_type' => 'string'],
            ['key' => 'business_address', 'section' => 'business', 'value_type' => 'text'],
            ['key' => 'business_opening_hours', 'section' => 'business', 'value_type' => 'text'],
            ['key' => 'payment_display_name', 'section' => 'payment', 'value_type' => 'string'],
            ['key' => 'payment_instructions', 'section' => 'payment', 'value_type' => 'text'],
            ['key' => 'payment_upi_id', 'section' => 'payment', 'value_type' => 'string'],
            ['key' => 'payment_whatsapp_number', 'section' => 'payment', 'value_type' => 'string'],
            ['key' => 'pages_about', 'section' => 'pages', 'value_type' => 'text'],
            ['key' => 'pages_contact', 'section' => 'pages', 'value_type' => 'text'],
            ['key' => 'pages_faq', 'section' => 'pages', 'value_type' => 'text'],
            ['key' => 'pages_terms', 'section' => 'pages', 'value_type' => 'text'],
            ['key' => 'pages_privacy', 'section' => 'pages', 'value_type' => 'text'],
        ];

        foreach ($rows as $row) {
            DB::table('website_settings')->insert([
                ...$row,
                'value' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
