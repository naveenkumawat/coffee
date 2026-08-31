<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_notification_logs', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('order_id')->constrained('ingredients')->nullOnDelete();
            $table->foreignId('inventory_refill_request_id')->nullable()->after('ingredient_id')->constrained('inventory_refill_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_refill_request_id');
            $table->dropConstrainedForeignId('ingredient_id');
        });
    }
};
