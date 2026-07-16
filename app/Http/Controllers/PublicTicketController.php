<?php

namespace App\Http\Controllers;

use App\Mail\NewTicketAlertMail;
use App\Mail\TicketConfirmationMail;
use App\Models\Designation;
use App\Models\Facility;
use App\Models\IssueType;
use App\Models\PriorityLevel;
use App\Models\Region;
use App\Models\SupportSystem;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketStatusLog;
use App\Support\AttachmentSecurityService;
use App\Support\AuditService;
use App\Support\DuplicateTicketDetectionService;
use App\Support\PublicSubmissionSecurityEventService;
use App\Support\SubmissionQuarantineService;
use App\Support\SubmissionRateLimitService;
use App\Support\SubmissionRiskService;
use App\Support\TicketNumberService;
use App\Support\TurnstileVerificationService;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicTicketController extends Controller
{
    public function __construct(
        private readonly TicketNumberService $ticketNumberService,
        private readonly AuditService $auditService,
        private readonly TurnstileVerificationService $turnstileVerificationService,
        private readonly SubmissionRateLimitService $submissionRateLimitService,
        private readonly AttachmentSecurityService $attachmentSecurityService,
        private readonly SubmissionRiskService $submissionRiskService,
        private readonly DuplicateTicketDetectionService $duplicateTicketDetectionService,
        private readonly SubmissionQuarantineService $submissionQuarantineService,
        private readonly PublicSubmissionSecurityEventService $securityEventService,
    ) {
    }

    public function create(Request $request): View
    {
        $submissionUuid = (string) Str::uuid();
        $request->session()->put('public_ticket_submission_uuid', $submissionUuid);
        $request->session()->put('public_ticket_rendered_at', now()->timestamp);
        $turnstileEnabled = $this->turnstileConfigured();

        return view('tickets.create', [
            'submissionUuid' => $submissionUuid,
            'formRenderedAt' => now()->timestamp,
            'turnstileSiteKey' => config('cis_submission.turnstile.site_key'),
            'turnstileEnabled' => $turnstileEnabled,
            'selectedSystem' => null,
            'designations' => Designation::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'systems' => SupportSystem::query()->where('active', true)->orderBy('sort_order')->get(),
            'regions' => Region::query()->where('active', true)->orderBy('sort_order')->get(),
            'facilities' => Facility::query()->where('active', true)->orderBy('sort_order')->get(),
            'issueTypes' => IssueType::query()->where('active', true)->orderBy('sort_order')->get(),
            'priorityLevels' => PriorityLevel::query()->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $turnstileEnabled = $this->turnstileConfigured();
        $submissionUuid = (string) $request->string('submission_uuid');
        $sessionUuid = (string) $request->session()->get('public_ticket_submission_uuid');

        if ($submissionUuid === '' || $sessionUuid === '' || ! hash_equals($sessionUuid, $submissionUuid)) {
            throw ValidationException::withMessages([
                'submission_uuid' => 'Your request could not be submitted at this time. Please refresh the page and try again.',
            ]);
        }

        if ($cachedResult = Cache::get('public-submission-result:'.$submissionUuid)) {
            return $this->duplicateSubmissionResponse($cachedResult);
        }

        $honeypotFilled = filled($request->input('website_url'));
        $elapsedSeconds = max(0, now()->timestamp - (int) $request->integer('form_rendered_at'));
        $fastSubmission = $elapsedSeconds < config('cis_submission.min_form_completion_seconds');

        $normalizedEmail = mb_strtolower(trim((string) $request->input('email')));
        $rateLimit = $this->submissionRateLimitService->ensureAllowed($request, $normalizedEmail);

        if (! $rateLimit['allowed']) {
            $this->securityEventService->log(
                'RATE_LIMIT_EXCEEDED',
                $request,
                $submissionUuid,
                $normalizedEmail,
                null,
                ['reason' => $rateLimit['reason'], 'scope' => $rateLimit['scope']],
                'blocked'
            );

            abort(Response::HTTP_TOO_MANY_REQUESTS, 'Too many requests have been submitted within a short period. Please wait and try again. For urgent assistance, contact the ICT Support Team.');
        }

        $validated = $request->validate([
            'submission_uuid' => ['required', 'uuid'],
            'system_id' => ['required', Rule::exists('support_systems', 'id')->where('active', true)],
            'designation_id' => ['required', Rule::exists('designations', 'id')->where('active', true)],
            'issue_started_at' => ['required', 'date', 'before_or_equal:today'],
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'lab_manager_name' => ['required', 'string', 'min:2', 'max:150'],
            'lab_manager_email' => ['required', 'email:rfc', 'max:254'],
            'region_id' => ['required', Rule::exists('regions', 'id')->where('active', true)],
            'district_name' => ['required', 'string', 'max:255'],
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('active', true)],
            'issue_type_id' => ['required', Rule::exists('issue_types', 'id')->where('active', true)],
            'priority_level_id' => ['required', Rule::exists('priority_levels', 'id')->where('active', true)],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'attachments.*' => ['nullable', 'file'],
            'cf-turnstile-response' => [$turnstileEnabled ? 'required' : 'nullable'],
        ]);

        $facility = Facility::query()->whereKey($validated['facility_id'])->firstOrFail();
        $invalidFacilityRegion = $facility->region_id !== null && (int) $facility->region_id !== (int) $validated['region_id'];

        if ($invalidFacilityRegion) {
            $this->securityEventService->log(
                'INVALID_MANAGED_LIST_VALUE',
                $request,
                $submissionUuid,
                $normalizedEmail,
                null,
                ['field' => 'facility_id', 'region_id' => $validated['region_id']],
                'rejected'
            );

            throw ValidationException::withMessages([
                'facility_id' => 'The selected facility does not match the selected region.',
            ]);
        }

        $turnstile = $this->turnstileVerificationService->verify($request->input('cf-turnstile-response'), $request->ip());

        if (! $turnstile['success']) {
            $this->securityEventService->log(
                'TURNSTILE_FAILED',
                $request,
                $submissionUuid,
                $normalizedEmail,
                null,
                ['error_code' => $turnstile['error_code']],
                'rejected'
            );

            throw ValidationException::withMessages([
                'turnstile' => 'We could not verify this submission. Please refresh the page and try again.',
            ]);
        }

        $duplicate = $this->duplicateTicketDetectionService->detect($validated);
        $this->attachmentSecurityService->validateFiles($request->file('attachments', []));
        $urlCount = preg_match_all('/https?:\/\/\S+/i', (string) $validated['description']);
        $emailDomain = substr(strrchr($normalizedEmail, '@') ?: '', 1);
        $trustedDomain = in_array($emailDomain, config('cis_submission.trusted_email_domains', []), true);
        $disposableEmail = in_array($emailDomain, config('cis_submission.disposable_email_domains', []), true);
        $repeatedCharacters = preg_match('/(.)\1{7,}/', (string) $validated['description']) === 1;

        $risk = $this->submissionRiskService->score([
            'honeypot_filled' => $honeypotFilled,
            'turnstile_failed' => false,
            'rate_limit_exceeded' => false,
            'fast_submission' => $fastSubmission,
            'disposable_email' => $disposableEmail,
            'url_count' => $urlCount,
            'duplicate_exact' => $duplicate['exact_duplicate'] !== null,
            'duplicate_possible' => $duplicate['possible_duplicate'] !== null,
            'invalid_facility_region' => $invalidFacilityRegion,
            'repeated_characters' => $repeatedCharacters,
            'trusted_domain' => $trustedDomain,
        ]);

        $status = TicketStatus::query()->where('code', 'new')->firstOrFail();
        $priority = PriorityLevel::query()->findOrFail($validated['priority_level_id']);

        $ticket = DB::transaction(function () use ($request, $validated, $status, $priority, $submissionUuid, $duplicate, $turnstile, $risk) {
            $ticket = Ticket::query()->create([
                ...$this->sanitizeValidatedData($validated),
                'ticket_number' => $this->ticketNumberService->generate(),
                'submission_uuid' => $submissionUuid,
                'content_fingerprint' => $duplicate['fingerprint'],
                'browser_info' => (string) $request->userAgent(),
                'device_info' => (string) $request->header('Sec-CH-UA-Platform', 'Unknown'),
                'ip_address' => $request->ip(),
                'status_id' => $status->id,
                'expected_resolution_date' => now()->addHours($priority->sla_hours)->toDateString(),
                'training_recommended' => false,
                'turnstile_verified' => true,
                'turnstile_error_code' => $turnstile['error_code'],
                'is_possible_duplicate' => $duplicate['possible_duplicate'] !== null,
                'duplicate_of_ticket_id' => $duplicate['possible_duplicate']?->id,
                'duplicate_confidence' => $duplicate['exact_duplicate'] ? 100 : ($duplicate['possible_duplicate'] ? 75 : null),
                'duplicate_review_status' => $duplicate['possible_duplicate'] ? 'PENDING' : null,
            ]);

            $this->submissionQuarantineService->apply($ticket, $risk);

            TicketStatusLog::query()->create([
                'ticket_id' => $ticket->id,
                'new_status_id' => $ticket->status_id,
            ]);

            return $ticket;
        });

        $uploadedAttachments = $this->attachmentSecurityService->storeForTicket($ticket, $request->file('attachments', []));

        if ($uploadedAttachments !== []) {
            $ticket->forceFill([
                'attachment_path' => $uploadedAttachments[0]->storage_path,
            ])->save();
        }

        $ticket->load(['system', 'status', 'designation', 'issueType', 'priorityLevel', 'attachments']);

        $this->auditService->log('ticket.created', $ticket, null, $ticket->toArray(), null, $request);

        if ($honeypotFilled) {
            $this->securityEventService->log('HONEYPOT_TRIGGERED', $request, $submissionUuid, $normalizedEmail, $risk['score'], [], 'quarantined', $ticket);
        }

        if ($fastSubmission) {
            $this->securityEventService->log('FAST_SUBMISSION', $request, $submissionUuid, $normalizedEmail, $risk['score'], ['elapsed_seconds' => $elapsedSeconds], 'flagged', $ticket);
        }

        if ($duplicate['possible_duplicate']) {
            $this->securityEventService->log('DUPLICATE_DETECTED', $request, $submissionUuid, $normalizedEmail, $risk['score'], ['matched_ticket_id' => $duplicate['possible_duplicate']->id], 'flagged', $ticket);
        }

        if ($ticket->submission_review_status !== 'QUARANTINED') {
            rescue(function () use ($ticket) {
                Mail::to(config('mail.from.address', 'ictsupport@cphl.go.ug'))
                    ->queue(new NewTicketAlertMail($ticket));
            }, report: false);

            if ($ticket->email) {
                rescue(function () use ($ticket) {
                    $mailer = Mail::to($ticket->email);
                    $mailer->queue(new TicketConfirmationMail($ticket));
                }, report: false);
            }
        } else {
            $this->securityEventService->log('SUBMISSION_QUARANTINED', $request, $submissionUuid, $normalizedEmail, $risk['score'], ['risk_level' => $risk['level']], 'quarantined', $ticket);
        }

        $result = [
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->submission_review_status === 'QUARANTINED'
                ? 'A similar request may already exist. Your submission has been received and will be reviewed by the ICT Support Team.'
                : 'Your ICT support request has been received successfully.',
        ];

        Cache::put('public-submission-result:'.$submissionUuid, $result, now()->addMinutes(config('cis_submission.idempotency_ttl_minutes')));
        $request->session()->forget(['public_ticket_submission_uuid', 'public_ticket_rendered_at']);

        return $this->duplicateSubmissionResponse($result);
    }

    private function turnstileConfigured(): bool
    {
        return (bool) config('cis_submission.turnstile.enabled')
            && filled(config('cis_submission.turnstile.site_key'))
            && filled(config('cis_submission.turnstile.secret_key'));
    }

    private function sanitizeValidatedData(array $validated): array
    {
        foreach (['full_name', 'lab_manager_name', 'district_name', 'description'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = $this->normalizeText((string) $validated[$field]);
            }
        }

        foreach (['phone', 'email', 'lab_manager_email'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim((string) $validated[$field]);
            }
        }

        unset($validated['cf-turnstile-response']);
        unset($validated['attachments']);
        unset($validated['submission_uuid']);

        return $validated;
    }

    private function normalizeText(string $value): string
    {
        $value = strip_tags($value);

        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function duplicateSubmissionResponse(array $result): RedirectResponse
    {
        return redirect()
            ->route('tickets.track')
            ->with('ticket_number', $result['ticket_number'])
            ->with('status', $result['status']);
    }
}
