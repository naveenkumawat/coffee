<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table): void {
            $table->string('placement', 20)->default('home')->after('max_items');
            $table->string('source_type', 40)->default('curated')->after('placement');
            $table->unsignedBigInteger('source_category_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('source_tag_id')->nullable()->after('source_category_id');
            $table->string('recommendation_context', 40)->nullable()->after('source_tag_id');
            $table->unsignedInteger('priority')->default(0)->after('recommendation_context');
            $table->json('targeting_rules')->nullable()->after('priority');
            $table->timestamp('starts_at')->nullable()->after('targeting_rules');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->boolean('dedupe_products')->default(true)->after('ends_at');
            $table->boolean('fallback_to_curated')->default(true)->after('dedupe_products');

            $table->foreign('source_category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('source_tag_id')->references('id')->on('product_tags')->nullOnDelete();
            $table->index(['placement', 'is_active', 'priority', 'sort_order'], 'home_sections_placement_active_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table): void {
            $table->dropIndex('home_sections_placement_active_priority_idx');
            $table->dropConstrainedForeignId('source_category_id');
            $table->dropConstrainedForeignId('source_tag_id');
            $table->dropColumn([
                'placement',
                'source_type',
                'recommendation_context',
                'priority',
                'targeting_rules',
                'starts_at',
                'ends_at',
                'dedupe_products',
                'fallback_to_curated',
            ]);
        });
    }
};
