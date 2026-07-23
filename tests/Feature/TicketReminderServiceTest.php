<?php

namespace Tests\Feature;

use App\Mail\TicketFeedbackReminderMail;
use App\Mail\TicketReminderMail;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Support\TicketReminderService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketReminderServiceTest extends TestCase
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

    public function test_it_sends_a_reminder_for_unresolved_assigned_tickets_older_than_three_days(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        Mail::fake();

        $support = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $assignedStatus = TicketStatus::query()->where('code', 'assigned')->firstOrFail();

        $ticket = $this->createTicket([
            'status_id' => $assignedStatus->id,
            'assigned_to' => $support->id,
            'assigned_at' => now()->subDays(4),
            'last_worked_at' => now()->subDays(4),
        ]);

        $counts = app(TicketReminderService::class)->sendScheduledReminders();

        $this->assertSame(1, $counts['unresolved']);
        $this->assertSame(0, $counts['feedback']);

        Mail::assertSent(TicketReminderMail::class, fn (TicketReminderMail $mail) => $mail->hasTo($support->email));

        $ticket->refresh();

        $this->assertNotNull($ticket->last_reminder_sent_at);
        $this->assertNull($ticket->feedback_reminder_sent_at);
    }

    public function test_it_sends_a_feedback_reminder_for_resolved_tickets_older_than_three_days(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        Mail::fake();

        $support = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $resolvedStatus = TicketStatus::query()->where('code', 'resolved')->firstOrFail();

        $ticket = $this->createTicket([
            'status_id' => $resolvedStatus->id,
            'assigned_to' => $support->id,
            'assigned_at' => now()->subDays(5),
            'last_worked_at' => now()->subDays(4),
            'resolved_at' => now()->subDays(4),
            'email' => 'requestor@example.com',
            'resolution_summary' => 'Password reset completed.',
        ]);

        $counts = app(TicketReminderService::class)->sendScheduledReminders();

        $this->assertSame(0, $counts['unresolved']);
        $this->assertSame(1, $counts['feedback']);

        Mail::assertSent(TicketFeedbackReminderMail::class, function (TicketFeedbackReminderMail $mail) {
            $feedbackUrl = $mail->content()->with['feedbackUrl'];

            return $mail->hasTo('requestor@example.com')
                && str_starts_with($feedbackUrl, rtrim((string) config('app.url'), '/').'/track/')
                && str_contains($feedbackUrl, '/feedback?signature=');
        });
        Mail::assertNotSent(TicketReminderMail::class);

        $ticket->refresh();

        $this->assertNotNull($ticket->feedback_reminder_sent_at);
    }

    public function test_it_does_not_send_a_feedback_reminder_once_feedback_exists(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        Mail::fake();

        $support = User::query()->where('email', 'support@cphl.go.ug')->firstOrFail();
        $resolvedStatus = TicketStatus::query()->where('code', 'resolved')->firstOrFail();

        $ticket = $this->createTicket([
            'status_id' => $resolvedStatus->id,
            'assigned_to' => $support->id,
            'assigned_at' => now()->subDays(5),
            'last_worked_at' => now()->subDays(4),
            'resolved_at' => now()->subDays(4),
            'email' => 'requestor@example.com',
        ]);

        $ticket->feedback()->create([
            'timeliness_rating' => 4,
            'completeness_rating' => 4,
            'overall_satisfaction_rating' => 5,
            'comments' => 'Good support.',
            'submitted_at' => now()->subDays(2),
        ]);

        $counts = app(TicketReminderService::class)->sendScheduledReminders();

        $this->assertSame(0, $counts['feedback']);
        Mail::assertNotSent(TicketFeedbackReminderMail::class);
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
