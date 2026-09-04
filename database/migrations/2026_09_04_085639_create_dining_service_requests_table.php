<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dining_session_id')->constrained('dining_sessions')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('cafe_tables');
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('order_assistance');
            $table->string('status', 30)->default('pending');
            $table->foreignId('preferred_waiter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completion_reason', 60)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['dining_session_id', 'status', 'type']);
            $table->index(['status', 'escalated_at', 'created_at']);
            $table->index(['preferred_waiter_user_id', 'status']);
            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_service_requests');
    }
};
