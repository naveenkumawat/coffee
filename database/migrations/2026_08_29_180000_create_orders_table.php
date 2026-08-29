<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->date('order_date');
            $table->unsignedInteger('daily_sequence');
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_barista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->text('customer_notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_for_pickup_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['order_date', 'daily_sequence']);
            $table->index(['status', 'placed_at']);
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_barista_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
