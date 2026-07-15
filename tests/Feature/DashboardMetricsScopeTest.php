<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_support_staff_dashboard_metrics_only_reflect_their_assigned_tickets(): void
    {
        $supportUser = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $otherUser = User::query()->where('email', 'developer@cphl.go.ug')->firstOrFail();

        $supportUser->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $basePayload = [
            'ticket_number' => 'TEMP',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Dashboard Reporter',
            'phone' => '0700000077',
            'email' => 'dashboard@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'manager@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Dashboard scoping test ticket.',
            'submission_review_status' => 'ACCEPTED',
        ];

        Ticket::query()->create([
            ...$basePayload,
            'ticket_number' => 'CIS-METRIC-001',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'assigned_to' => $supportUser->id,
        ]);

        Ticket::query()->create([
            ...$basePayload,
            'ticket_number' => 'CIS-METRIC-002',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'assigned_to' => $supportUser->id,
        ]);

        Ticket::query()->create([
            ...$basePayload,
            'ticket_number' => 'CIS-METRIC-003',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'assigned_to' => $otherUser->id,
        ]);

        Ticket::query()->create([
            ...$basePayload,
            'ticket_number' => 'CIS-METRIC-004',
            'status_id' => TicketStatus::query()->where('code', 'resolved')->value('id'),
            'assigned_to' => $otherUser->id,
            'resolved_at' => now(),
            'expected_resolution_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($supportUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Assigned Tickets');
        $response->assertSee('>2<', false);
        $response->assertSee('>0<', false);
    }
}
