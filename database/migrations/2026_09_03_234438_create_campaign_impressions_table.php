<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_impressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_key', 64)->nullable()->index();
            $table->string('session_key', 64)->nullable()->index();
            $table->string('event_type', 20);
            $table->string('placement', 40)->nullable();
            $table->string('request_id', 80)->nullable()->index();
            $table->string('cta_type', 40)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['campaign_id', 'customer_id', 'event_type', 'occurred_at'], 'campaign_imp_customer_idx');
            $table->index(['campaign_id', 'visitor_key', 'event_type', 'occurred_at'], 'campaign_imp_visitor_idx');
            $table->index(['campaign_id', 'session_key', 'event_type'], 'campaign_imp_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_impressions');
    }
};
