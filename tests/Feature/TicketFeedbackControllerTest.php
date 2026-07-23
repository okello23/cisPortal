<?php

namespace Tests\Feature;

use App\Mail\TicketFeedbackReceivedMail;
use App\Mail\TicketFeedbackThankYouMail;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Mail\Mailables\Attachment;
use Tests\TestCase;

class TicketFeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_requestor_can_submit_feedback_from_a_signed_link(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        Mail::fake();
        Storage::fake('local');

        $support = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $resolvedStatus = TicketStatus::query()->where('code', 'resolved')->firstOrFail();
        $closedStatus = TicketStatus::query()->where('code', 'closed')->firstOrFail();

        $ticket = $this->createTicket([
            'status_id' => $resolvedStatus->id,
            'assigned_to' => $support->id,
            'resolved_at' => now()->subHour(),
            'resolution_summary' => 'Updated the configuration and verified access.',
        ]);

        $url = $ticket->feedbackUrl('tickets.feedback.store');

        $response = $this->post($url, [
            'timeliness_rating' => 4,
            'completeness_rating' => 5,
            'overall_satisfaction_rating' => 4,
            'comments' => 'The support team was helpful and clear.',
        ]);

        $response->assertRedirect($ticket->feedbackUrl());
        $response->assertSessionHas('status');

        $ticket->refresh();

        $this->assertNotNull($ticket->feedback);
        $this->assertSame($closedStatus->id, $ticket->status_id);
        $this->assertNotNull($ticket->closed_at);
        $this->assertNotNull($ticket->feedback->incident_report_path);
        $this->assertNotNull($ticket->feedback->incident_report_generated_at);
        Storage::disk('local')->assertExists($ticket->feedback->incident_report_path);

        Mail::assertSent(TicketFeedbackReceivedMail::class, fn (TicketFeedbackReceivedMail $mail) => $mail->hasTo($support->email));
        Mail::assertSent(TicketFeedbackThankYouMail::class, function (TicketFeedbackThankYouMail $mail) use ($ticket) {
            $attachments = $mail->attachments();

            return $mail->hasTo($ticket->email)
                && count($attachments) === 1
                && $attachments[0]->isEquivalent(
                    Attachment::fromStorageDisk('local', $ticket->feedback->incident_report_path)
                        ->as($ticket->ticket_number.'-resolution-report.pdf')
                        ->withMime('application/pdf')
                );
        });
    }

    public function test_feedback_form_requires_a_valid_signature(): void
    {
        $ticket = $this->createTicket([
            'status_id' => TicketStatus::query()->where('code', 'resolved')->value('id'),
            'resolved_at' => now(),
        ]);

        $response = $this->get(route('tickets.feedback.show', ['ticket' => $ticket]));

        $response->assertForbidden();
    }

    public function test_a_generated_incident_report_can_be_downloaded_from_a_signed_tracking_link(): void
    {
        Storage::fake('local');

        $ticket = $this->createTicket([
            'status_id' => TicketStatus::query()->where('code', 'closed')->value('id'),
            'resolved_at' => now()->subHour(),
            'closed_at' => now(),
        ]);

        $ticket->feedback()->create([
            'timeliness_rating' => 4,
            'completeness_rating' => 4,
            'overall_satisfaction_rating' => 5,
            'comments' => 'Great support.',
            'incident_report_path' => 'reports/incident-resolution/'.$ticket->ticket_number.'.pdf',
            'incident_report_generated_at' => now(),
            'submitted_at' => now(),
        ]);

        Storage::disk('local')->put($ticket->feedback->incident_report_path, '%PDF-1.4 sample');

        $response = $this->get(URL::temporarySignedRoute(
            'tickets.report.download',
            now()->addHour(),
            ['ticket' => $ticket]
        ));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_completed_ticket_without_feedback_still_gets_a_downloadable_incident_report(): void
    {
        Storage::fake('local');

        $ticket = $this->createTicket([
            'status_id' => TicketStatus::query()->where('code', 'closed')->value('id'),
            'resolved_at' => now()->subHour(),
            'closed_at' => now(),
            'work_done' => 'Applied the correct facility mapping and regenerated outputs.',
        ]);

        $response = $this->get(URL::temporarySignedRoute(
            'tickets.report.download',
            now()->addHour(),
            ['ticket' => $ticket]
        ));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        Storage::disk('local')->assertExists('reports/incident-resolution/'.$ticket->ticket_number.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicket(array $overrides = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'ticket_number' => 'TCK-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'system_id' => SupportSystem::query()->where('code', 'rds')->value('id'),
            'full_name' => 'Test Requestor',
            'phone' => '0700000010',
            'email' => 'test.requestor@example.com',
            'issue_type_id' => IssueType::query()->where('code', 'ict-issue')->value('id'),
            'priority_level_id' => PriorityLevel::query()->where('code', 'medium')->value('id'),
            'description' => 'Need help with system access.',
            'status_id' => TicketStatus::query()->where('code', 'new')->value('id'),
        ], $overrides));
    }
}
