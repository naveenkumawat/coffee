<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->string('payment_method_previous', 30)->nullable()->after('payment_method');
            $table->timestamp('payment_method_changed_at')->nullable()->after('payment_method_previous');
            $table->foreignId('payment_method_changed_by_id')
                ->nullable()
                ->after('payment_method_changed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dining_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_changed_by_id');
            $table->dropColumn([
                'payment_method_previous',
                'payment_method_changed_at',
            ]);
        });
    }
};
