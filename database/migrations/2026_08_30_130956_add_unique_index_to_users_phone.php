<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing phones to digits-only before unique index.
        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (object $user): void {
                $normalized = preg_replace('/\D+/', '', (string) $user->phone) ?: null;

                DB::table('users')->where('id', $user->id)->update(['phone' => $normalized]);
            });

        // Clear duplicate non-null phones (keep lowest id).
        $duplicates = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('phone');

        foreach ($duplicates as $phone) {
            $ids = DB::table('users')->where('phone', $phone)->orderBy('id')->pluck('id');
            $ids->skip(1)->each(function (int $id): void {
                DB::table('users')->where('id', $id)->update(['phone' => null]);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
        });
    }
};
