<?php

namespace Tests\Feature;

use App\Models\Pipeline;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_reference_data_endpoint_returns_phase_one_options(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/v1/reference-data')
            ->assertOk()
            ->assertJsonPath('lead_sources.0.value', 'telegram')
            ->assertJsonPath('lead_sources.5.value', 'site_widget')
            ->assertJsonPath('request_types.0.value', 'analyses')
            ->assertJsonPath('payment_statuses.0.value', 'unpaid')
            ->assertJsonPath('payment_statuses.1.value', 'paid')
            ->assertJsonStructure([
                'branches',
                'lead_sources' => [['value', 'label']],
                'request_types' => [['value', 'label']],
                'payment_statuses' => [['value', 'label']],
            ]);
    }

    public function test_seeded_pipelines_match_first_part_of_specification(): void
    {
        $leads = Pipeline::where('type', 'leads')->firstOrFail();
        $deals = Pipeline::where('type', 'deals')->firstOrFail();

        $this->assertSame([
            'Необработан (Новый)',
            'Взято в работу',
            'Недозвон / Пауза',
            'Квалификация (Выявление потребности)',
            'Конвертация (Успех)',
            'Некачественный лид (Брак/Спам)',
        ], $leads->stages()->pluck('name')->all());

        $this->assertSame([
            'Новая заявка (Подбор времени)',
            'Записан (Визит запланирован)',
            'Подтверждение (Напоминание)',
            'Визит состоялся (В клинике)',
            'Результаты готовы',
            'Результаты отправлены (Успех)',
            'Неявка (Не дошел)',
            'Отказ / Отмена',
        ], $deals->stages()->pluck('name')->all());

        $conversionStage = $leads->stages()->where('sort_order', 5)->firstOrFail();
        $badLeadStage = $leads->stages()->where('sort_order', 6)->firstOrFail();
        $noShowStage = $deals->stages()->where('sort_order', 7)->firstOrFail();
        $cancelledStage = $deals->stages()->where('sort_order', 8)->firstOrFail();

        $this->assertTrue($conversionStage->is_final);
        $this->assertFalse($conversionStage->is_fail);
        $this->assertTrue($badLeadStage->is_final);
        $this->assertTrue($badLeadStage->is_fail);
        $this->assertFalse($noShowStage->is_final);
        $this->assertFalse($noShowStage->is_fail);
        $this->assertTrue($cancelledStage->is_final);
        $this->assertTrue($cancelledStage->is_fail);
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@clinic.test')->firstOrFail();
    }
}
