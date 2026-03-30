<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ТЗ: "Перечень анализов" — отдельная таблица, т.к. список множественный
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Название анализа
            $table->string('code')->nullable(); // Код в МИС (для будущей интеграции)
            $table->decimal('price', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deal_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 8, 2)->default(0); // Цена на момент добавления
            $table->timestamps();

            $table->unique(['deal_id', 'analysis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_analyses');
        Schema::dropIfExists('analyses');
    }
};
