<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketResolutionFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_root_cause_analysis_is_required_when_resolving_a_ticket(): void
    {
        $supportUser = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $supportUser->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $ticket = Ticket::query()->create([
            'ticket_number' => 'CIS-RES-001',
            'system_id' => \App\Models\SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => \App\Models\Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Resolution Reporter',
            'phone' => '0700000088',
            'email' => 'resolution@example.com',
            'lab_manager_name' => 'Manager',
            'lab_manager_email' => 'manager@example.com',
            'region_id' => \App\Models\Region::query()->firstOrFail()->id,
            'district_name' => 'Kyegegwa',
            'facility_id' => \App\Models\Facility::query()->firstOrFail()->id,
            'issue_type_id' => \App\Models\IssueType::query()->firstOrFail()->id,
            'priority_level_id' => \App\Models\PriorityLevel::query()->firstOrFail()->id,
            'description' => 'Resolution validation test.',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'submission_review_status' => 'ACCEPTED',
            'assigned_to' => $supportUser->id,
        ]);

        $response = $this->actingAs($supportUser)
            ->from(route('admin.tickets.show', $ticket))
            ->put(route('admin.tickets.update', $ticket), [
                'status_id' => TicketStatus::query()->where('code', 'resolved')->value('id'),
                'assigned_to' => $supportUser->id,
                'expected_resolution_date' => now()->addDay()->toDateString(),
                'resolution_category_id' => \App\Models\ResolutionCategory::query()->firstOrFail()->id,
                'work_done' => 'Updated the configuration and tested the import.',
                'recommendations' => 'Validate the CSV template before upload.',
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHasErrors('root_cause_analysis');
        $response->assertSessionHasErrors('verification_testing');
        $response->assertSessionHasErrors('data_loss_risk');
        $response->assertSessionHasErrors('services_disrupted');
    }
}
