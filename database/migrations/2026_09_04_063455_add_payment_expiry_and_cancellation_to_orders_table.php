<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('payment_expires_at')->nullable()->after('placed_at');
            $table->string('cancellation_source', 40)->nullable()->after('cancelled_at');
            $table->string('cancellation_reason', 80)->nullable()->after('cancellation_source');

            $table->index(['status', 'payment_status', 'payment_expires_at'], 'orders_pending_payment_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_pending_payment_expiry_idx');
            $table->dropColumn(['payment_expires_at', 'cancellation_source', 'cancellation_reason']);
        });
    }
};
