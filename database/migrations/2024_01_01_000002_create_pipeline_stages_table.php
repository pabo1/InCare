<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');                        // Название стадии
            $table->string('slug')->nullable();            // new, in_work, no_answer, ...
            $table->string('color')->default('#6B7280');   // Цвет для Kanban
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type')->default('normal');     // normal | success | fail
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->boolean('is_final')->default(false);
            $table->boolean('is_fail')->default(false);

            $table->unique(['pipeline_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
