<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BugAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.bug_assistant_model', 'gpt-5.6-sol');
    }

    public function test_assigned_staff_can_analyze_a_ticket_screenshot_and_messages_are_saved(): void
    {
        Storage::fake('local');
        $staff = $this->supportUser();
        $ticket = $this->ticket($staff);
        $path = 'tickets/test/error.png';
        Storage::disk('local')->put($path, 'image-bytes');

        $attachment = TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'original_filename' => 'error.png',
            'detected_mime_type' => 'image/png',
            'extension' => 'png',
            'file_size' => 11,
            'checksum' => hash('sha256', 'image-bytes'),
            'status' => 'SCAN_FAILED',
        ]);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test_123',
                'output_text' => "What I can see\nA timeout error is visible.",
                'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
            ]),
        ]);

        $response = $this->actingAs($staff)->postJson(
            route('admin.tickets.ai-assistant.store', $ticket),
            [
                'message' => 'Analyze this screenshot.',
                'attachment_ids' => [$attachment->id],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', "What I can see\nA timeout error is visible.");

        $this->assertDatabaseCount('ticket_ai_messages', 2);
        $this->assertDatabaseHas('ticket_ai_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'role' => 'user',
        ]);
        $this->assertDatabaseHas('ticket_ai_messages', [
            'ticket_id' => $ticket->id,
            'role' => 'assistant',
            'provider_response_id' => 'resp_test_123',
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $content = data_get($payload, 'input.0.content', []);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['model'] === 'gpt-5.6-sol'
                && collect($content)->contains(fn ($item) => $item['type'] === 'input_image'
                    && str_starts_with($item['image_url'], 'data:image/png;base64,'));
        });
    }

    public function test_unassigned_staff_cannot_use_the_ticket_assistant(): void
    {
        Http::fake();
        $assignedStaff = $this->supportUser();
        $ticket = $this->ticket($assignedStaff);
        $otherStaff = User::query()->where('email', 'developer@cphl.go.ug')->firstOrFail();
        $otherStaff->forceFill(['force_password_change' => false, 'password_changed_at' => now()])->save();

        $this->actingAs($otherStaff)
            ->postJson(route('admin.tickets.ai-assistant.store', $ticket), [
                'message' => 'Analyze this ticket.',
                'attachment_ids' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ticket_ai_messages', 0);
        Http::assertNothingSent();
    }

    public function test_missing_api_configuration_returns_a_clear_error_without_saving_chat(): void
    {
        config()->set('services.openai.api_key', null);
        $staff = $this->supportUser();
        $ticket = $this->ticket($staff);

        $this->actingAs($staff)
            ->postJson(route('admin.tickets.ai-assistant.store', $ticket), [
                'message' => 'Analyze this ticket.',
                'attachment_ids' => [],
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI assistant is not configured. Add OPENAI_API_KEY to the server environment.');

        $this->assertDatabaseCount('ticket_ai_messages', 0);
    }

    private function supportUser(): User
    {
        $user = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $user->forceFill(['force_password_change' => false, 'password_changed_at' => now()])->save();

        return $user;
    }

    private function ticket(User $assignedStaff): Ticket
    {
        return Ticket::query()->create([
            'ticket_number' => 'CIS-AI-TEST-001',
            'system_id' => SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => Designation::query()->firstOrFail()->id,
            'issue_started_at' => now()->toDateString(),
            'full_name' => 'Bug Reporter',
            'phone' => '0700000010',
            'email' => 'reporter@example.com',
            'lab_manager_name' => 'Lab Manager',
            'lab_manager_email' => 'manager@example.com',
            'region_id' => Region::query()->firstOrFail()->id,
            'district_name' => 'Kampala',
            'facility_id' => Facility::query()->firstOrFail()->id,
            'issue_type_id' => IssueType::query()->firstOrFail()->id,
            'priority_level_id' => PriorityLevel::query()->firstOrFail()->id,
            'description' => 'The application displays an error when results are submitted.',
            'status_id' => TicketStatus::query()->where('code', 'assigned')->value('id'),
            'submission_review_status' => 'ACCEPTED',
            'assigned_to' => $assignedStaff->id,
        ]);
    }
}
