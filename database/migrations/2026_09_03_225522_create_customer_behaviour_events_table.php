<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visitor_identities', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 64);
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();

            $table->unique('visitor_key');
            $table->index(['customer_id', 'claimed_at']);
        });

        Schema::create('customer_behaviour_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 64);
            $table->string('source', 16);
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_key', 64)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('page_context', 160)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unique('idempotency_key');
            $table->index(['visitor_key', 'occurred_at']);
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['product_id', 'event_type', 'occurred_at']);
            $table->index(['product_category_id', 'event_type', 'occurred_at']);
            $table->index(['order_id', 'event_type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_behaviour_events');
        Schema::dropIfExists('customer_visitor_identities');
    }
};
