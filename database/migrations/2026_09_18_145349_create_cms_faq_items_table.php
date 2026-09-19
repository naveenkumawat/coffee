<?php

use App\Enums\CmsPageKey;
use App\Enums\WebsiteSettingKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_faq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['cms_page_id', 'sort_order']);
        });

        $faqPageId = DB::table('cms_pages')->where('key', CmsPageKey::Faq->value)->value('id');
        $legacy = DB::table('website_settings')
            ->where('key', WebsiteSettingKey::PagesFaq->value)
            ->value('value');

        if (! is_numeric($faqPageId) || ! is_string($legacy) || trim($legacy) === '') {
            return;
        }

        $now = now();
        $sort = 0;

        foreach (preg_split("/\n\s*\n/", trim($legacy)) ?: [] as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            $lines = array_values(array_filter(array_map('trim', preg_split("/\n/", $block) ?: [])));

            if ($lines === []) {
                continue;
            }

            $question = preg_replace('/^[QA][:.\-–—]\s*/i', '', $lines[0]) ?? $lines[0];
            $answer = implode("\n", array_slice($lines, 1));
            $answer = preg_replace('/^[QA][:.\-–—]\s*/i', '', $answer) ?? $answer;
            $answer = trim((string) $answer);

            if ($answer === '') {
                continue;
            }

            DB::table('cms_faq_items')->insert([
                'cms_page_id' => (int) $faqPageId,
                'question' => mb_substr(trim((string) $question), 0, 255),
                'answer' => $answer,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sort += 10;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_faq_items');
    }
};
