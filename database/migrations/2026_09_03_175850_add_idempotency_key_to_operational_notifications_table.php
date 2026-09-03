<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_notifications', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('uuid');
            $table->unique('idempotency_key', 'ops_notifications_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('operational_notifications', function (Blueprint $table) {
            $table->dropUnique('ops_notifications_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
