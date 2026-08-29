<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('checkout_token', 64)->nullable()->after('assigned_barista_id');
            $table->string('customer_name')->nullable()->after('customer_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 50)->nullable()->after('customer_email');
            $table->string('pickup_name')->nullable()->after('customer_phone');
            $table->string('pickup_phone', 50)->nullable()->after('pickup_name');
            $table->text('pickup_notes')->nullable()->after('customer_notes');

            $table->unique('checkout_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn([
                'checkout_token',
                'customer_name',
                'customer_email',
                'customer_phone',
                'pickup_name',
                'pickup_phone',
                'pickup_notes',
            ]);
        });
    }
};
