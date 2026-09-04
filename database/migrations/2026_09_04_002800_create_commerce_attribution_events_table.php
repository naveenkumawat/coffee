<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_attribution_events', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('request_id', 80);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_key', 64)->nullable();
            $table->string('strategy', 64)->nullable();
            $table->string('reason', 64)->nullable();
            $table->string('placement', 64)->nullable();
            $table->string('context', 64)->nullable();
            $table->string('attribution_mode', 32)->default('direct');
            $table->string('stage', 32);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedInteger('units')->default(0);
            $table->decimal('revenue_amount', 12, 2)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['source_type', 'stage', 'occurred_at'], 'cae_source_stage_occurred_idx');
            $table->index(['source_id', 'stage', 'occurred_at'], 'cae_source_id_stage_occurred_idx');
            $table->index(['strategy', 'stage', 'occurred_at'], 'cae_strategy_stage_occurred_idx');
            $table->index(['placement', 'stage', 'occurred_at'], 'cae_placement_stage_occurred_idx');
            $table->index(['customer_id', 'occurred_at'], 'cae_customer_occurred_idx');
            $table->index(['visitor_key', 'occurred_at'], 'cae_visitor_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_attribution_events');
    }
};
