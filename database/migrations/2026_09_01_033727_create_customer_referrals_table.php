<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code_snapshot', 16);
            $table->string('status', 20)->default('registered');
            $table->foreignId('qualified_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            $table->unique('referred_user_id');
            $table->index(['referrer_user_id', 'status']);
            $table->index(['status', 'qualified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
    }
};
