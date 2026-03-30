<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Поля для SLA (Service Level Agreement)
            $table->timestamp('sla_started_at')->nullable()->after('created_at')->comment('Время начала отсчета SLA');
            $table->string('sla_source', 50)->nullable()->after('sla_started_at')->comment('Источник лида: website, chat, incoming_call, missed_call');
            $table->string('sla_priority', 20)->nullable()->after('sla_source')->comment('Приоритет: low, normal, high');
            $table->text('sla_note')->nullable()->after('sla_priority')->comment('Заметки по SLA');

            // Поля для отслеживания недозвонов
            $table->integer('missed_call_attempts')->default(0)->after('sla_note')->comment('Количество попыток звонка');
            $table->timestamp('last_call_attempt_at')->nullable()->after('missed_call_attempts')->comment('Время последней попытки звонка');

            // Поля для отслеживания отказов
            $table->string('rejection_reason', 100)->nullable()->after('last_call_attempt_at')->comment('Причина отказа: Дорого / Неудобное время / Ушли к конкурентам / Спам / Не выходит на связь');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'sla_started_at',
                'sla_source',
                'sla_priority',
                'sla_note',
                'missed_call_attempts',
                'last_call_attempt_at',
                'rejection_reason'
            ]);
        });
    }
};
