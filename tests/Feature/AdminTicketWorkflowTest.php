<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_new_ticket_hides_status_and_orders_assignees_by_role_priority(): void
    {
        $manager = User::query()->where('email', 'manager@cphl.go.ug')->firstOrFail();
        $manager->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $ticket = Ticket::query()->create([
            'ticket_number' => 'CIS-WORKFLOW-001',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Workflow Reporter',
            'phone' => '0700000099',
            'email' => 'workflow@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'labmanager@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Workflow display test.',
            'status_id' => TicketStatus::query()->where('code', 'new')->value('id'),
            'submission_review_status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($manager)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSee('label class="form-label">Status', false);
        $response->assertSee('name="status_id"', false);

        $content = $response->getContent();

        $this->assertIsString($content);
        preg_match('/<select name="assigned_to" class="form-select">(.*?)<\/select>/s', $content, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $assignToSelect = $matches[1];

        $this->assertLessThan(
            strpos($assignToSelect, 'Developer'),
            strpos($assignToSelect, 'ICT Support Staff')
        );
        $this->assertLessThan(
            strpos($assignToSelect, 'ICT Admin'),
            strpos($assignToSelect, 'Developer')
        );
        $this->assertLessThan(
            strpos($assignToSelect, 'ICT Manager'),
            strpos($assignToSelect, 'ICT Admin')
        );
    }

    public function test_assigned_ticket_only_offers_escalated_and_resolved_statuses(): void
    {
        $supportUser = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $supportUser->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $ticket = Ticket::query()->create([
            'ticket_number' => 'CIS-WORKFLOW-002',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Workflow Reporter',
            'phone' => '0700000098',
            'email' => 'workflow2@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'labmanager2@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Assigned workflow test.',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'submission_review_status' => 'ACCEPTED',
            'assigned_to' => $supportUser->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertIsString($content);
        preg_match('/<select name="status_id" class="form-select" id="status_id" required>(.*?)<\/select>/s', $content, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $statusSelect = $matches[1];

        $this->assertStringContainsString('Escalated', $statusSelect);
        $this->assertStringContainsString('Resolved', $statusSelect);
        $this->assertStringNotContainsString('Assigned', $statusSelect);
        $this->assertStringNotContainsString('In Progress', $statusSelect);
        $this->assertStringNotContainsString('Pending User', $statusSelect);
        $this->assertStringNotContainsString('Closed', $statusSelect);
        $this->assertStringNotContainsString('Reopened', $statusSelect);
    }
}
