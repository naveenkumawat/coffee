<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('tax_enabled_snapshot')->default(false)->after('discount_total');
            $table->string('tax_label_snapshot', 40)->nullable()->after('tax_enabled_snapshot');
            $table->decimal('tax_percent_snapshot', 5, 2)->nullable()->after('tax_label_snapshot');
            $table->boolean('tax_inclusive_snapshot')->default(false)->after('tax_percent_snapshot');
            $table->decimal('taxable_amount', 12, 2)->default(0)->after('tax_inclusive_snapshot');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('taxable_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_enabled_snapshot',
                'tax_label_snapshot',
                'tax_percent_snapshot',
                'tax_inclusive_snapshot',
                'taxable_amount',
                'tax_amount',
            ]);
        });
    }
};
