<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_preparations', function (Blueprint $table): void {
            $table->index(['station', 'status', 'created_at'], 'order_preparations_station_status_created_index');
            $table->index(['status', 'ready_at'], 'order_preparations_status_ready_at_index');
        });

        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->index(['opened_at', 'status'], 'dining_sessions_opened_at_status_index');
            $table->index(['billing_requested_at', 'status'], 'dining_sessions_billing_requested_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_preparations', function (Blueprint $table): void {
            $table->dropIndex('order_preparations_station_status_created_index');
            $table->dropIndex('order_preparations_status_ready_at_index');
        });

        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->dropIndex('dining_sessions_opened_at_status_index');
            $table->dropIndex('dining_sessions_billing_requested_status_index');
        });
    }
};
