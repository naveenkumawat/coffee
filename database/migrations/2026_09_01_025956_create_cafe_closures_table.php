<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_closures', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('type', 40)->default('holiday');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('customer_message', 500)->nullable();
            $table->string('internal_note', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'cafe_closures_active_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_closures');
    }
};
