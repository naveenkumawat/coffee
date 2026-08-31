<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_notification_logs')) {
            return;
        }

        // Fresh installs already have the channel-aware schema from the create migration.
        if (Schema::hasColumn('customer_notification_logs', 'channel')
            && Schema::hasColumn('customer_notification_logs', 'recipient_phone')
            && Schema::hasColumn('customer_notification_logs', 'provider_message_id')) {
            return;
        }

        if (Schema::hasColumn('customer_notification_logs', 'unique_key')) {
            try {
                Schema::table('customer_notification_logs', function (Blueprint $table) {
                    $table->dropUnique(['unique_key']);
                });
            } catch (Throwable) {
                // Index may already be absent on partially upgraded databases.
            }
        }

        Schema::table('customer_notification_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_notification_logs', 'channel')) {
                $table->string('channel', 16)->default('email')->after('type');
            }

            if (! Schema::hasColumn('customer_notification_logs', 'recipient_phone')) {
                $table->string('recipient_phone', 32)->nullable()->after('recipient_email');
            }

            if (! Schema::hasColumn('customer_notification_logs', 'provider_message_id')) {
                $table->string('provider_message_id', 128)->nullable()->after('recipient_phone');
            }
        });

        try {
            Schema::table('customer_notification_logs', function (Blueprint $table) {
                $table->unique(['unique_key', 'channel']);
            });
        } catch (Throwable) {
            // Composite unique may already exist.
        }
    }

    public function down(): void
    {
        // Non-destructive: keep channel columns if present.
    }
};
