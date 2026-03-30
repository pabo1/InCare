<?php

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmStageRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs($this->adminUser());
    }

    public function test_lead_requires_request_type_and_branch_for_qualification_and_cannot_be_moved_to_success_directly(): void
    {
        $newStage = $this->leadStage(1);
        $qualificationStage = $this->leadStage(4);
        $successStage = $this->leadStage(5);

        $lead = Lead::create([
            'pipeline_id' => $newStage->pipeline_id,
            'pipeline_stage_id' => $newStage->id,
            'user_id' => $this->adminUser()->id,
            'name' => 'Проверка лида',
        ]);

        $this->postJson("/api/v1/leads/{$lead->id}/stage", [
            'stage_id' => $qualificationStage->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['request_type', 'branch']);

        $this->postJson("/api/v1/leads/{$lead->id}/stage", [
            'stage_id' => $qualificationStage->id,
            'request_type' => Lead::REQUEST_ANALYSES,
            'branch' => 'Центр',
        ])->assertOk()
            ->assertJsonPath('pipeline_stage_id', $qualificationStage->id);

        $this->postJson("/api/v1/leads/{$lead->id}/stage", [
            'stage_id' => $successStage->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['stage_id']);
    }

    public function test_deal_stage_rules_require_booking_data_paid_status_and_cancel_reason(): void
    {
        $newStage = $this->dealStage(1);
        $scheduledStage = $this->dealStage(2);
        $completedStage = $this->dealStage(4);
        $cancelledStage = $this->dealStage(8);
        $analysis = Analysis::query()->firstOrFail();

        $deal = Deal::create([
            'pipeline_id' => $newStage->pipeline_id,
            'pipeline_stage_id' => $newStage->id,
            'user_id' => $this->adminUser()->id,
            'name' => 'Проверка сделки',
            'payment_status' => Deal::PAYMENT_UNPAID,
        ]);

        $this->postJson("/api/v1/deals/{$deal->id}/stage", [
            'stage_id' => $scheduledStage->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['branch', 'appointment_at', 'analyses']);

        $this->postJson("/api/v1/deals/{$deal->id}/stage", [
            'stage_id' => $scheduledStage->id,
            'branch' => 'Центр',
            'appointment_at' => now()->addDay()->toISOString(),
            'analyses' => [$analysis->id],
        ])->assertOk()
            ->assertJsonPath('pipeline_stage_id', $scheduledStage->id);

        $this->postJson("/api/v1/deals/{$deal->id}/stage", [
            'stage_id' => $completedStage->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_status']);

        $this->postJson("/api/v1/deals/{$deal->id}/stage", [
            'stage_id' => $completedStage->id,
            'payment_status' => Deal::PAYMENT_PAID,
        ])->assertOk()
            ->assertJsonPath('pipeline_stage_id', $completedStage->id);

        $cancelledDeal = Deal::create([
            'pipeline_id' => $newStage->pipeline_id,
            'pipeline_stage_id' => $newStage->id,
            'user_id' => $this->adminUser()->id,
            'name' => 'Отмена сделки',
            'payment_status' => Deal::PAYMENT_UNPAID,
        ]);

        $this->postJson("/api/v1/deals/{$cancelledDeal->id}/stage", [
            'stage_id' => $cancelledStage->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['cancel_reason']);

        $this->postJson("/api/v1/deals/{$cancelledDeal->id}/stage", [
            'stage_id' => $cancelledStage->id,
            'cancel_reason' => 'Клиент передумал',
        ])->assertOk()
            ->assertJsonPath('pipeline_stage_id', $cancelledStage->id);
    }

    public function test_conversion_creates_contact_for_lead_when_missing(): void
    {
        $qualificationStage = $this->leadStage(4);
        $dealStage = $this->dealStage(1);

        $lead = Lead::create([
            'pipeline_id' => $qualificationStage->pipeline_id,
            'pipeline_stage_id' => $qualificationStage->id,
            'user_id' => $this->adminUser()->id,
            'name' => 'Лид без контакта',
            'phone' => '+998900001122',
            'source' => Lead::SOURCE_TELEGRAM,
            'request_type' => Lead::REQUEST_ANALYSES,
            'branch' => 'Центр',
        ]);

        $response = $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'deal_stage_id' => $dealStage->id,
        ])->assertCreated();

        $lead->refresh();
        $dealId = $response->json('deal.id');
        $deal = Deal::query()->findOrFail($dealId);

        $this->assertNotNull($lead->contact_id);
        $this->assertSame($lead->contact_id, $deal->contact_id);
    }

    private function leadStage(int $sortOrder): PipelineStage
    {
        return PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'leads'))
            ->where('sort_order', $sortOrder)
            ->firstOrFail();
    }

    private function dealStage(int $sortOrder): PipelineStage
    {
        return PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'deals'))
            ->where('sort_order', $sortOrder)
            ->firstOrFail();
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@clinic.test')->firstOrFail();
    }
}