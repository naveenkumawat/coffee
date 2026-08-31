<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('channel', 16)->default('email');
            $table->string('unique_key');
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 32)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['unique_key', 'channel']);
            $table->index(['order_id', 'type']);
            $table->index(['order_id', 'channel', 'type']);
            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notification_logs');
    }
};
