<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_operating_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekday', 'sort_order'], 'cafe_operating_hours_weekday_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_operating_hours');
    }
};
