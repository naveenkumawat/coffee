<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('ordering_blocked')->default(false)->after('cash_takeaway_allowed');
            $table->timestamp('ordering_blocked_at')->nullable()->after('ordering_blocked');
            $table->string('ordering_blocked_reason', 500)->nullable()->after('ordering_blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ordering_blocked', 'ordering_blocked_at', 'ordering_blocked_reason']);
        });
    }
};
