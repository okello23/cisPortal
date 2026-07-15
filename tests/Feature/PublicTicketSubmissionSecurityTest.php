<?php

namespace Tests\Feature;

use App\Mail\NewTicketAlertMail;
use App\Mail\TicketConfirmationMail;
use App\Models\Designation;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicTicketSubmissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_valid_public_submission_creates_ticket_and_queues_emails(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->withSession([
            'public_ticket_submission_uuid' => '7d77d291-2ecf-4bc8-9b60-4088dcd27402',
        ])->post(route('tickets.store'), $this->validPayload([
            'submission_uuid' => '7d77d291-2ecf-4bc8-9b60-4088dcd27402',
            'cf-turnstile-response' => 'valid-token',
            'attachments' => [
                UploadedFile::fake()->image('issue.png'),
            ],
        ]));

        $response->assertRedirect(route('tickets.track'));
        $response->assertSessionHas('ticket_number');

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('ACCEPTED', $ticket->submission_review_status);
        $this->assertTrue($ticket->turnstile_verified);
        $this->assertTrue($ticket->attachments()->exists());

        Mail::assertQueued(NewTicketAlertMail::class);
        Mail::assertQueued(TicketConfirmationMail::class);
    }

    public function test_valid_public_submission_allows_multiple_attachments_within_limit(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $response = $this->withSession([
            'public_ticket_submission_uuid' => '14d8a369-1791-4cdb-88a7-00e586112111',
        ])->post(route('tickets.store'), $this->validPayload([
            'submission_uuid' => '14d8a369-1791-4cdb-88a7-00e586112111',
            'cf-turnstile-response' => 'valid-token',
            'attachments' => [
                UploadedFile::fake()->image('issue-1.png'),
                UploadedFile::fake()->image('issue-2.png'),
                UploadedFile::fake()->image('issue-3.png'),
                UploadedFile::fake()->create('combined-notes.pdf', 200, 'application/pdf'),
            ],
        ]));

        $response->assertRedirect(route('tickets.track'));

        $ticket = Ticket::query()->firstOrFail();

        $this->assertCount(4, $ticket->attachments);
    }

    public function test_public_submission_still_allows_single_attachment_when_attachment_config_is_missing(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        config()->set('cis_submission.attachments.max_files', null);
        config()->set('cis_submission.attachments.max_size_mb', null);
        config()->set('cis_submission.attachments.allowed_extensions', null);
        config()->set('cis_submission.attachments.allow_plain_text', null);

        $response = $this->withSession([
            'public_ticket_submission_uuid' => 'ef4d46fb-e874-4324-ba30-d7a8920ef123',
        ])->post(route('tickets.store'), $this->validPayload([
            'submission_uuid' => 'ef4d46fb-e874-4324-ba30-d7a8920ef123',
            'cf-turnstile-response' => 'valid-token',
            'attachments' => [
                UploadedFile::fake()->image('issue.png'),
            ],
        ]));

        $response->assertRedirect(route('tickets.track'));

        $ticket = Ticket::query()->firstOrFail();

        $this->assertTrue($ticket->attachments()->exists());
    }

    public function test_missing_turnstile_token_is_rejected(): void
    {
        $response = $this->from(route('tickets.create'))
            ->withSession([
                'public_ticket_submission_uuid' => '30d6e8b4-3d3f-447e-bd6f-8b2534fed95f',
            ])->post(route('tickets.store'), $this->validPayload([
                'submission_uuid' => '30d6e8b4-3d3f-447e-bd6f-8b2534fed95f',
                'cf-turnstile-response' => '',
            ]));

        $response->assertRedirect(route('tickets.create'));
        $response->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_honeypot_submission_is_quarantined_and_suppresses_email(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->withSession([
            'public_ticket_submission_uuid' => '99859ed1-240a-46e4-bfe9-e6ac6e7ce84d',
        ])->post(route('tickets.store'), $this->validPayload([
            'submission_uuid' => '99859ed1-240a-46e4-bfe9-e6ac6e7ce84d',
            'cf-turnstile-response' => 'valid-token',
            'website_url' => 'https://spam.example.test',
        ]))->assertRedirect(route('tickets.track'));

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame('QUARANTINED', $ticket->submission_review_status);
        $this->assertTrue($ticket->is_suspected_spam);

        Mail::assertNothingQueued();
    }

    public function test_repeated_submission_uuid_returns_original_result_without_creating_another_ticket(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $payload = $this->validPayload([
            'submission_uuid' => 'd56f6fdb-7843-43fc-8a30-5920798af3c5',
            'cf-turnstile-response' => 'valid-token',
        ]);

        $this->withSession(['public_ticket_submission_uuid' => 'd56f6fdb-7843-43fc-8a30-5920798af3c5'])
            ->post(route('tickets.store'), $payload)
            ->assertRedirect(route('tickets.track'));

        $this->withSession(['public_ticket_submission_uuid' => 'd56f6fdb-7843-43fc-8a30-5920798af3c5'])
            ->post(route('tickets.store'), $payload)
            ->assertRedirect(route('tickets.track'));

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_rate_limit_blocks_excessive_public_submissions(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        config()->set('cis_submission.rate_limits.per_ip_10_minutes', 1);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])
            ->withSession(['public_ticket_submission_uuid' => 'd78f5028-b9ea-4551-aa74-64f1a873c0c0'])
            ->post(route('tickets.store'), $this->validPayload([
                'submission_uuid' => 'd78f5028-b9ea-4551-aa74-64f1a873c0c0',
                'cf-turnstile-response' => 'valid-token',
            ]))
            ->assertRedirect(route('tickets.track'));

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])
            ->withSession(['public_ticket_submission_uuid' => '57190c32-1e16-4b64-a0cf-14efab2a8d18'])
            ->post(route('tickets.store'), $this->validPayload([
                'submission_uuid' => '57190c32-1e16-4b64-a0cf-14efab2a8d18',
                'cf-turnstile-response' => 'valid-token',
            ]))
            ->assertStatus(429);
    }

    public function test_missing_trusted_ip_limit_config_falls_back_without_crashing(): void
    {
        Mail::fake();
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        config()->set('cis_submission.trusted_networks', ['127.0.0.3']);
        config()->set('cis_submission.rate_limits.trusted_per_ip_10_minutes', null);
        config()->set('cis_submission.rate_limits.per_ip_10_minutes', 2);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.3'])
            ->withSession(['public_ticket_submission_uuid' => 'b25785f1-dfcf-4641-a242-1fc61d9acfd6'])
            ->post(route('tickets.store'), $this->validPayload([
                'submission_uuid' => 'b25785f1-dfcf-4641-a242-1fc61d9acfd6',
                'cf-turnstile-response' => 'valid-token',
            ]))
            ->assertRedirect(route('tickets.track'));

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_only_managers_can_access_the_anti_spam_dashboard(): void
    {
        $supportUser = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $supportUser->forceFill([
            'force_password_change' => false,
            'password_changed_at' => now(),
        ])->save();

        $this->actingAs($supportUser)
            ->get(route('admin.anti-spam.dashboard'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'system_id' => SupportSystem::query()->where('code', 'rds')->value('id'),
            'designation_id' => Designation::query()->value('id'),
            'issue_started_at' => now()->subDay()->toDateString(),
            'full_name' => 'Jane Requestor',
            'phone' => '0700123456',
            'email' => 'jane.requestor@example.com',
            'lab_manager_name' => 'John Manager',
            'lab_manager_email' => 'john.manager@example.com',
            'region_id' => Region::query()->where('code', 'eastern')->value('id'),
            'district_name' => 'Mbale',
            'facility_id' => Facility::query()->where('code', 'mbale-rrh')->value('id'),
            'issue_type_id' => IssueType::query()->where('code', 'ict-issue')->value('id'),
            'priority_level_id' => PriorityLevel::query()->where('code', 'medium')->value('id'),
            'description' => 'Unable to submit test results because the system keeps timing out.',
            'website_url' => '',
            'form_rendered_at' => now()->subSeconds(6)->timestamp,
        ], $overrides);
    }
}
