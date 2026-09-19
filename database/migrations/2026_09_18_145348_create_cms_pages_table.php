<?php

use App\Enums\CmsPageKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('title');
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach (CmsPageKey::ordered() as $pageKey) {
            $legacy = DB::table('website_settings')
                ->where('key', $pageKey->legacySettingKey()->value)
                ->value('value');
            $legacyText = is_string($legacy) ? trim($legacy) : '';

            DB::table('cms_pages')->insert([
                'key' => $pageKey->value,
                'title' => $pageKey->title(),
                'seo_title' => null,
                'meta_description' => null,
                'body' => $pageKey === CmsPageKey::Faq ? null : ($legacyText !== '' ? $legacyText : null),
                'is_published' => $legacyText !== '',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
