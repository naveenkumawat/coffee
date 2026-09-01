<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('dining_session_id')
                ->nullable()
                ->after('cafe_table_id')
                ->constrained('dining_sessions')
                ->nullOnDelete();
            $table->unsignedInteger('dining_round_number')->nullable()->after('dining_session_id');

            $table->index(['dining_session_id', 'dining_round_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dining_session_id');
            $table->dropColumn('dining_round_number');
        });
    }
};
