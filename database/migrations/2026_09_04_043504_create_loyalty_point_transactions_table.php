<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_point_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('points');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 120);
            $table->string('reason_code', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique('idempotency_key', 'loyalty_txn_idempotency_uq');
            $table->index(['customer_id', 'occurred_at'], 'loyalty_txn_customer_occurred_idx');
            $table->index(['loyalty_account_id', 'occurred_at'], 'loyalty_txn_account_occurred_idx');
            $table->index(['source_type', 'source_id'], 'loyalty_txn_source_idx');
            $table->index(['type', 'occurred_at'], 'loyalty_txn_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_transactions');
    }
};
