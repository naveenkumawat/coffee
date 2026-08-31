<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cafe_table_id')->nullable()->after('fulfilment_method')->constrained('cafe_tables')->nullOnDelete();
            $table->string('table_name_snapshot', 120)->nullable()->after('cafe_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cafe_table_id');
            $table->dropColumn('table_name_snapshot');
        });
    }
};
