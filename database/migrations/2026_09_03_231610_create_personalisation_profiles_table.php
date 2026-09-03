<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personalisation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('visitor_key', 64)->nullable();
            $table->unsignedSmallInteger('profile_version')->default(1);
            $table->unsignedInteger('event_sample_count')->default(0);
            $table->unsignedInteger('order_sample_count')->default(0);
            $table->boolean('has_sufficient_evidence')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->json('category_affinities')->nullable();
            $table->json('product_affinities')->nullable();
            $table->json('flavour_affinities')->nullable();
            $table->json('preferred_variants')->nullable();
            $table->json('addon_preferences')->nullable();
            $table->json('recent_product_ids')->nullable();
            $table->json('recent_category_ids')->nullable();
            $table->json('purchase_frequency')->nullable();
            $table->json('repeat_purchase_product_ids')->nullable();
            $table->json('spend_band')->nullable();
            $table->json('time_of_day_preferences')->nullable();
            $table->json('signals_meta')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
            $table->unique('visitor_key');
            $table->index('calculated_at');
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalisation_profiles');
    }
};
