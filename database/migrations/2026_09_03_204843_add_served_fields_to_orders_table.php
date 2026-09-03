<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('served_at')->nullable()->after('ready_for_pickup_at');
            $table->foreignId('served_by_user_id')
                ->nullable()
                ->after('served_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('served_by_user_id');
            $table->dropColumn('served_at');
        });
    }
};
