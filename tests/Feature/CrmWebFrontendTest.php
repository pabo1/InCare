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

    private function adminUser(): User
    {
        return User::query()->where('email', 'admin@clinic.test')->firstOrFail();
    }
}