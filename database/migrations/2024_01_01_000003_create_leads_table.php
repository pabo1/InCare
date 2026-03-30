<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Воронка и стадия
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();

            // Ответственный оператор
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Контакт (если уже определён)
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();

            // Основные данные лида
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('source')->nullable();      // telegram | sipuni | instagram | form | site_widget

            // Квалификация (стадия 4)
            $table->string('request_type')->nullable(); // Анализы | Врач | Медсестра | Инфо-запрос
            $table->string('branch')->nullable();       // Филиал / Регион

            // Статус качества
            $table->string('quality')->nullable();      // null | spam | wrong_number | ad

            // Внешние ID для мессенджеров
            $table->string('telegram_chat_id')->nullable();
            $table->string('external_id')->nullable();  // ID из внешней системы (Sipuni call_id и т.д.)

            // Метаданные
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_stage_id', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
