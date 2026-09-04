<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table): void {
            $table->integer('lifetime_adjusted_points')->default(0)->after('lifetime_redeemed_points');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE loyalty_accounts MODIFY available_points INT NOT NULL DEFAULT 0');
        }
        // SQLite ignores UNSIGNED; existing integer storage already allows negatives.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE loyalty_accounts MODIFY available_points INT UNSIGNED NOT NULL DEFAULT 0');
        }

        Schema::table('loyalty_accounts', function (Blueprint $table): void {
            $table->dropColumn('lifetime_adjusted_points');
        });
    }
};
