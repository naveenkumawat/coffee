<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('internal_label')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('surface', 20)->default('popup')->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_type', 40)->default('close');
            $table->unsignedBigInteger('cta_product_id')->nullable();
            $table->unsignedBigInteger('cta_category_id')->nullable();
            $table->unsignedBigInteger('cta_promotion_id')->nullable();
            $table->string('cta_internal_path', 120)->nullable();
            $table->unsignedInteger('priority')->default(0)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('frequency_policy', 40)->default('once_per_session');
            $table->unsignedInteger('cooldown_hours')->nullable();
            $table->unsignedInteger('max_impressions')->nullable();
            $table->json('placement_rules');
            $table->json('targeting_rules');
            $table->json('trigger_rules');
            $table->string('attribution_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cta_product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('cta_category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('cta_promotion_id')->references('id')->on('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
