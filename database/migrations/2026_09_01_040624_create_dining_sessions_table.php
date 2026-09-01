<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_number')->unique();
            $table->foreignId('cafe_table_id')->constrained('cafe_tables');
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->unsignedTinyInteger('guest_count')->nullable();
            $table->string('table_name_snapshot');
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot', 50)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('billing_requested_at')->nullable();
            $table->timestamp('bill_generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_status', 30)->nullable();
            $table->decimal('subtotal_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('taxable_amount', 12, 2)->nullable();
            $table->boolean('tax_enabled_snapshot')->nullable();
            $table->string('tax_label_snapshot')->nullable();
            $table->decimal('tax_percent_snapshot', 8, 2)->nullable();
            $table->boolean('tax_inclusive_snapshot')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('payment_proof_disk')->nullable();
            $table->string('payment_proof_mime')->nullable();
            $table->unsignedInteger('payment_proof_size')->nullable();
            $table->timestamp('payment_proof_uploaded_at')->nullable();
            $table->text('payment_proof_rejection_notes')->nullable();
            $table->foreignId('payment_received_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'cafe_table_id']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_sessions');
    }
};
