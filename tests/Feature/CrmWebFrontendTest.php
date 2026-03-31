<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmWebFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login_and_login_page_renders(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('demoCredentials.email', 'admin@clinic.test')
            );
    }

    public function test_user_can_log_in_and_open_dashboard_and_lists(): void
    {
        $response = $this->post(route('login.store'), [
            'email' => 'admin@clinic.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('crm.dashboard'));

        $this->get(route('crm.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->has('pipelines')
            );

        $this->get(route('crm.leads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Leads/Index')
                ->has('leads.data')
            );

        $this->get(route('crm.deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Index')
                ->has('deals.data')
            );
    }

    public function test_authenticated_user_can_open_lead_and_deal_detail_pages(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $leadStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'leads'))
            ->orderBy('sort_order')
            ->firstOrFail();

        $dealStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'deals'))
            ->orderBy('sort_order')
            ->firstOrFail();

        $lead = Lead::create([
            'pipeline_id' => $leadStage->pipeline_id,
            'pipeline_stage_id' => $leadStage->id,
            'user_id' => $user->id,
            'name' => 'Лид для фронта',
            'source' => 'telegram',
            'request_type' => 'analyses',
        ]);

        $deal = Deal::create([
            'pipeline_id' => $dealStage->pipeline_id,
            'pipeline_stage_id' => $dealStage->id,
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'name' => 'Сделка для фронта',
            'payment_status' => Deal::PAYMENT_UNPAID,
        ]);

        $this->get(route('crm.leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Leads/Show')
                ->where('lead.name', 'Лид для фронта')
            );

        $this->get(route('crm.deals.show', $deal))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Deals/Show')
                ->where('deal.name', 'Сделка для фронта')
            );
    }

    public function test_dashboard_shows_only_active_leads_and_upcoming_non_final_deals(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);

        $activeLeadStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'leads'))
            ->where('is_final', false)
            ->orderBy('sort_order')
            ->firstOrFail();

        $finalLeadStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'leads'))
            ->where('is_final', true)
            ->orderBy('sort_order')
            ->firstOrFail();

        $activeDealStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'deals'))
            ->where('is_final', false)
            ->orderBy('sort_order')
            ->firstOrFail();

        $finalDealStage = PipelineStage::query()
            ->whereHas('pipeline', fn ($query) => $query->where('type', 'deals'))
            ->where('is_final', true)
            ->orderBy('sort_order')
            ->firstOrFail();

        Lead::create([
            'pipeline_id' => $activeLeadStage->pipeline_id,
            'pipeline_stage_id' => $activeLeadStage->id,
            'user_id' => $user->id,
            'name' => 'Активный лид',
            'source' => 'telegram',
            'request_type' => 'analyses',
        ]);

        Lead::create([
            'pipeline_id' => $finalLeadStage->pipeline_id,
            'pipeline_stage_id' => $finalLeadStage->id,
            'user_id' => $user->id,
            'name' => 'Закрытый лид',
            'source' => 'telegram',
            'request_type' => 'analyses',
        ]);

        Deal::create([
            'pipeline_id' => $activeDealStage->pipeline_id,
            'pipeline_stage_id' => $activeDealStage->id,
            'user_id' => $user->id,
            'name' => 'Предстоящая сделка',
            'appointment_at' => now()->addHour(),
            'payment_status' => Deal::PAYMENT_UNPAID,
        ]);

        Deal::create([
            'pipeline_id' => $activeDealStage->pipeline_id,
            'pipeline_stage_id' => $activeDealStage->id,
            'user_id' => $user->id,
            'name' => 'Прошедшая сделка',
            'appointment_at' => now()->subHour(),
            'payment_status' => Deal::PAYMENT_UNPAID,
        ]);

        Deal::create([
            'pipeline_id' => $finalDealStage->pipeline_id,
            'pipeline_stage_id' => $finalDealStage->id,
            'user_id' => $user->id,
            'name' => 'Финальная сделка',
            'appointment_at' => now()->addDay(),
            'payment_status' => Deal::PAYMENT_PAID,
        ]);

        $this->get(route('crm.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('recentLeads', fn ($leads) => collect($leads)->pluck('name')->all() === ['Активный лид'])
                ->where('upcomingDeals', fn ($deals) => collect($deals)->pluck('name')->all() === ['Предстоящая сделка'])
            );
    }

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@clinic.test')->firstOrFail();
    }
}
