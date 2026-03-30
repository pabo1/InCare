<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPipeline(
            type: 'leads',
            name: 'Лиды',
            stages: [
                [
                    'name' => 'Необработан (Новый)',
                    'sort_order' => 1,
                    'color' => '#94a3b8',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Взято в работу',
                    'sort_order' => 2,
                    'color' => '#3b82f6',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Недозвон / Пауза',
                    'sort_order' => 3,
                    'color' => '#f59e0b',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Квалификация (Выявление потребности)',
                    'sort_order' => 4,
                    'color' => '#8b5cf6',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Конвертация (Успех)',
                    'sort_order' => 5,
                    'color' => '#10b981',
                    'is_final' => true,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Некачественный лид (Брак/Спам)',
                    'sort_order' => 6,
                    'color' => '#ef4444',
                    'is_final' => true,
                    'is_fail' => true,
                ],
            ],
        );

        $this->syncPipeline(
            type: 'deals',
            name: 'Сдача анализов',
            stages: [
                [
                    'name' => 'Новая заявка (Подбор времени)',
                    'sort_order' => 1,
                    'color' => '#94a3b8',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Записан (Визит запланирован)',
                    'sort_order' => 2,
                    'color' => '#3b82f6',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Подтверждение (Напоминание)',
                    'sort_order' => 3,
                    'color' => '#8b5cf6',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Визит состоялся (В клинике)',
                    'sort_order' => 4,
                    'color' => '#10b981',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Результаты готовы',
                    'sort_order' => 5,
                    'color' => '#06b6d4',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Результаты отправлены (Успех)',
                    'sort_order' => 6,
                    'color' => '#10b981',
                    'is_final' => true,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Неявка (Не дошел)',
                    'sort_order' => 7,
                    'color' => '#f59e0b',
                    'is_final' => false,
                    'is_fail' => false,
                ],
                [
                    'name' => 'Отказ / Отмена',
                    'sort_order' => 8,
                    'color' => '#ef4444',
                    'is_final' => true,
                    'is_fail' => true,
                ],
            ],
        );
    }

    private function syncPipeline(string $type, string $name, array $stages): void
    {
        $pipeline = Pipeline::query()->firstOrNew(['type' => $type]);
        $pipeline->fill([
            'name' => $name,
            'is_active' => true,
        ]);
        $pipeline->save();

        foreach ($stages as $stage) {
            $record = PipelineStage::query()->firstOrNew([
                'pipeline_id' => $pipeline->id,
                'sort_order' => $stage['sort_order'],
            ]);

            $record->fill($stage);
            $record->pipeline_id = $pipeline->id;
            $record->save();
        }
    }
}
