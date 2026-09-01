<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_round_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dining_session_id')->constrained('dining_sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['dining_session_id', 'product_variant_id'], 'dining_draft_session_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_round_drafts');
    }
};
