<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_notification_recipients')) {
            return;
        }

        Schema::create('operational_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_notification_id')
                ->constrained('operational_notifications')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamp('broadcast_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('action_started_at')->nullable();
            $table->timestamp('action_completed_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['operational_notification_id', 'user_id'], 'ops_notification_user_unique');
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'acknowledged_at']);
            $table->index(['user_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notification_recipients');
    }
};
