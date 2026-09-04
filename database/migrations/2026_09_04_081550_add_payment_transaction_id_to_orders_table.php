<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_transaction_id', 64)
                ->nullable()
                ->after('payment_reference');
            $table->index('payment_transaction_id', 'orders_payment_transaction_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_payment_transaction_id_idx');
            $table->dropColumn('payment_transaction_id');
        });
    }
};
