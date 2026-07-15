<?php

namespace App\Http\Controllers;

use App\Models\PublicSubmissionSecurityEvent;
use App\Models\SubmissionBlock;
use App\Models\Ticket;
use App\Support\AuditService;
use App\Support\PublicSubmissionSecurityEventService;
use App\Support\SubmissionRateLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSubmissionAdminController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly PublicSubmissionSecurityEventService $securityEventService,
        private readonly SubmissionRateLimitService $submissionRateLimitService,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $this->authorizeManager();

        $rangeStart = now()->subDays(30);
        $events = PublicSubmissionSecurityEvent::query()->where('created_at', '>=', $rangeStart)->get();
        $tickets = Ticket::query()->where('created_at', '>=', $rangeStart)->get();

        return view('admin.anti-spam.dashboard', [
            'summary' => [
                'total_public_submissions' => $tickets->count(),
                'accepted_submissions' => $tickets->where('submission_review_status', 'ACCEPTED')->count(),
                'medium_risk_submissions' => $tickets->where('submission_risk_level', 'MEDIUM')->count(),
                'quarantined_submissions' => $tickets->where('submission_review_status', 'QUARANTINED')->count(),
                'blocked_submissions' => $events->where('action_taken', 'blocked')->count(),
                'confirmed_spam' => $tickets->where('submission_review_status', 'REJECTED_SPAM')->count(),
                'possible_duplicates' => $tickets->where('is_possible_duplicate', true)->count(),
                'rejected_attachments' => 0,
                'turnstile_failures' => $events->where('event_type', 'TURNSTILE_FAILED')->count(),
                'rate_limit_violations' => $events->where('event_type', 'RATE_LIMIT_EXCEEDED')->count(),
            ],
            'topSourceIps' => $events->groupBy('source_ip')->map->count()->sortDesc()->take(5),
            'topSystems' => $tickets->groupBy('system_id')->map->count()->sortDesc()->take(5),
            'topEmailDomains' => $tickets->map(function (Ticket $ticket) {
                return substr(strrchr((string) $ticket->email, '@') ?: '', 1);
            })->filter()->countBy()->sortDesc()->take(5),
            'trends' => $tickets->groupBy(fn (Ticket $ticket) => $ticket->created_at->toDateString())->map->count()->sortKeys(),
            'blocks' => SubmissionBlock::query()->where('is_active', true)->latest()->get(),
        ]);
    }

    public function quarantine(Request $request): View
    {
        $this->authorizeManager();

        return view('admin.anti-spam.quarantine', [
            'tickets' => Ticket::query()
                ->with(['system', 'facility', 'region', 'duplicateParent', 'attachments'])
                ->where('submission_review_status', 'QUARANTINED')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function updateQuarantine(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeManager();
        abort_unless($ticket->submission_review_status === 'QUARANTINED', 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject_spam', 'release'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = Auth::user();
        $previous = $ticket->submission_review_status;

        if ($validated['action'] === 'reject_spam') {
            $ticket->submission_review_status = 'REJECTED_SPAM';
            $ticket->is_suspected_spam = true;
        } else {
            $ticket->submission_review_status = 'ACCEPTED';
            $ticket->quarantined_at = null;
            $ticket->quarantine_reason = null;
            $ticket->released_to_queue_at = now();
        }

        $ticket->reviewed_at = now();
        $ticket->reviewed_by = $user->id;
        $ticket->save();

        $this->securityEventService->log(
            $validated['action'] === 'reject_spam' ? 'SUBMISSION_REJECTED_AS_SPAM' : 'SUBMISSION_APPROVED',
            $request,
            $ticket->submission_uuid,
            $ticket->email,
            $ticket->submission_risk_score,
            ['reason' => $validated['reason'] ?? null],
            $validated['action'],
            $ticket,
            $user->id
        );

        $this->auditService->log(
            $validated['action'] === 'reject_spam' ? 'QUARANTINE_REJECTED' : 'QUARANTINE_APPROVED',
            $ticket,
            ['submission_review_status' => $previous],
            ['submission_review_status' => $ticket->submission_review_status, 'reason' => $validated['reason'] ?? null],
            $user->id,
            $request
        );

        return back()->with('status', 'Submission review updated successfully.');
    }

    public function events(Request $request): View
    {
        $this->authorizeManager();

        return view('admin.anti-spam.events', [
            'events' => PublicSubmissionSecurityEvent::query()->latest()->paginate(25),
        ]);
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'block_type' => ['required', Rule::in(['ip', 'email', 'domain', 'phone', 'fingerprint'])],
            'block_value' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $block = SubmissionBlock::query()->create([
            ...$validated,
            'blocked_at' => now(),
            'blocked_by' => Auth::id(),
            'is_active' => true,
        ]);

        $this->auditService->log('BLOCK_CREATED', $block, null, $block->toArray(), Auth::id(), $request);

        return back()->with('status', 'Temporary block created successfully.');
    }

    public function destroyBlock(Request $request, SubmissionBlock $block): RedirectResponse
    {
        $this->authorizeManager();

        $block->update(['is_active' => false]);
        $this->submissionRateLimitService->clearTemporaryBlock($block->block_type, $block->block_value);
        $this->auditService->log('BLOCK_REMOVED', $block, ['is_active' => true], ['is_active' => false], Auth::id(), $request);

        return back()->with('status', 'Block removed successfully.');
    }

    private function authorizeManager(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasAnyRole(['ict_admin', 'ict_manager']), 403);
    }
}
