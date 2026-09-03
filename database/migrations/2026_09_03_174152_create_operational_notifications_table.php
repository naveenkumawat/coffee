<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 64);
            $table->string('category', 64);
            $table->string('priority', 16)->default('normal');
            $table->string('title');
            $table->text('message');
            $table->boolean('action_required')->default(false);
            $table->string('action_code', 64)->nullable();
            $table->string('action_url', 500)->nullable();
            $table->nullableMorphs('subject');
            $table->nullableMorphs('actor');
            $table->timestamp('resolved_at')->nullable();
            $table->nullableMorphs('resolved_by');
            $table->string('resolution_action', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['category', 'priority']);
            $table->index(['action_required', 'resolved_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notifications');
    }
};
