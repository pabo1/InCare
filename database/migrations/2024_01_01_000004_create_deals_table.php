<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();

            // Воронка и стадия
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();

            // Ответственный
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Контакт и лид-источник
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete(); // откуда пришла сделка

            // Название сделки
            $table->string('name');

            // ТЗ: Регион / Филиал
            $table->string('branch')->nullable();

            // ТЗ: Дата и время записи
            $table->dateTime('appointment_at')->nullable();

            // ТЗ: Статус оплаты
            $table->string('payment_status')->default('unpaid'); // unpaid | paid | partial

            // ТЗ: Причина отказа (стадия 8 — Отказ/Отмена)
            $table->string('cancel_reason')->nullable();

            // Сумма сделки
            $table->decimal('amount', 10, 2)->default(0);

            // Telegram chat для этой сделки (чтобы отправлять напоминания)
            $table->string('telegram_chat_id')->nullable();

            // Метаданные / доп. поля
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_stage_id', 'appointment_at']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
