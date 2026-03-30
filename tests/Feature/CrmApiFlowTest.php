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

class CrmApiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_deal_can_be_created_with_analyses(): void
    {
        Sanctum::actingAs($this->adminUser());

        $stage = PipelineStage::whereHas('pipeline', fn($query) => $query->where('type', 'deals'))
            ->orderBy('sort_order')
            ->firstOrFail();

        $analysisA = Analysis::where('code', 'CBC')->firstOrFail();
        $analysisB = Analysis::where('code', 'GLU')->firstOrFail();

        $response = $this->postJson('/api/v1/deals', [
            'name' => 'Проверка сделки',
            'pipeline_stage_id' => $stage->id,
            'analyses' => [
                $analysisA->id,
                ['id' => $analysisB->id, 'price' => 9.75],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Проверка сделки')
            ->assertJsonCount(2, 'analyses');

        $deal = Deal::with('analyses')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$analysisA->id, $analysisB->id],
            $deal->analyses->pluck('id')->all(),
        );

        $this->assertEquals(15.00, (float) $deal->analyses->firstWhere('id', $analysisA->id)->pivot->price);
        $this->assertEquals(9.75, (float) $deal->analyses->firstWhere('id', $analysisB->id)->pivot->price);
    }

    public function test_lead_can_be_converted_to_deal_with_analyses_and_moves_to_success_stage(): void
    {
        $user = $this->adminUser();
        Sanctum::actingAs($user);

        $leadStage = PipelineStage::whereHas('pipeline', fn($query) => $query->where('type', 'leads'))
            ->orderBy('sort_order')
            ->firstOrFail();

        $dealStage = PipelineStage::whereHas('pipeline', fn($query) => $query->where('type', 'deals'))
            ->orderBy('sort_order')
            ->firstOrFail();

        $lead = Lead::create([
            'pipeline_id' => $leadStage->pipeline_id,
            'pipeline_stage_id' => $leadStage->id,
            'user_id' => $user->id,
            'name' => 'Лид на конвертацию',
            'source' => 'telegram',
            'request_type' => Lead::REQUEST_ANALYSES,
            'branch' => 'Центр',
        ]);

        $lead->stageHistory()->create([
            'pipeline_stage_id' => $leadStage->id,
            'user_id' => $user->id,
            'entered_at' => now(),
        ]);

        $analysis = Analysis::where('code', 'VITD')->firstOrFail();

        $response = $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'deal_stage_id' => $dealStage->id,
            'analyses' => [$analysis->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('deal.lead_id', $lead->id)
            ->assertJsonCount(1, 'deal.analyses');

        $lead->refresh();
        $deal = Deal::with('analyses')->where('lead_id', $lead->id)->firstOrFail();

        $successStage = PipelineStage::where('pipeline_id', $leadStage->pipeline_id)
            ->where('is_final', true)
            ->where('is_fail', false)
            ->orderBy('sort_order')
            ->firstOrFail();

        $this->assertSame($successStage->id, $lead->pipeline_stage_id);
        $this->assertSame($deal->id, $lead->meta['converted_deal_id']);
        $this->assertTrue($deal->analyses->contains('id', $analysis->id));
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@clinic.test')->firstOrFail();
    }
}