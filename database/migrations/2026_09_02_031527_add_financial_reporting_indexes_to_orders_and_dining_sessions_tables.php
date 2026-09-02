<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(
                ['payment_confirmed_at', 'payment_status', 'status'],
                'orders_payment_confirmed_reporting_idx',
            );
        });

        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->index(
                ['paid_at', 'payment_status'],
                'dining_sessions_paid_reporting_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_payment_confirmed_reporting_idx');
        });

        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->dropIndex('dining_sessions_paid_reporting_idx');
        });
    }
};
