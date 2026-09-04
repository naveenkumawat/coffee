<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('loyalty_reward_id')
                ->nullable()
                ->after('referral_coupon_reward_id')
                ->constrained('loyalty_rewards')
                ->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('loyalty_reward_id')
                ->nullable()
                ->after('discount_total')
                ->constrained('loyalty_rewards')
                ->nullOnDelete();
            $table->string('loyalty_reward_name_snapshot')->nullable()->after('loyalty_reward_id');
            $table->string('loyalty_reward_type_snapshot', 40)->nullable()->after('loyalty_reward_name_snapshot');
            $table->unsignedInteger('loyalty_reward_points_cost_snapshot')->nullable()->after('loyalty_reward_type_snapshot');
            $table->decimal('loyalty_discount_amount', 12, 2)->default(0)->after('loyalty_reward_points_cost_snapshot');
            $table->string('loyalty_reward_description_snapshot', 500)->nullable()->after('loyalty_discount_amount');
            $table->json('loyalty_reward_snapshot')->nullable()->after('loyalty_reward_description_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loyalty_reward_id');
            $table->dropColumn([
                'loyalty_reward_name_snapshot',
                'loyalty_reward_type_snapshot',
                'loyalty_reward_points_cost_snapshot',
                'loyalty_discount_amount',
                'loyalty_reward_description_snapshot',
                'loyalty_reward_snapshot',
            ]);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loyalty_reward_id');
        });
    }
};
