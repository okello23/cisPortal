<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketStatusLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Storage;

class IncidentResolutionReportService
{
    public function generateForTicket(Ticket $ticket): string
    {
        $ticket->loadMissing($this->ticketRelationships());

        $path = 'reports/incident-resolution/'.$ticket->ticket_number.'.pdf';

        Storage::disk('local')->makeDirectory('reports/incident-resolution');
        Storage::disk('local')->put($path, $this->renderPdf($this->reportData($ticket, $ticket->feedback)));

        return $path;
    }

    public function generateForFeedback(Ticket $ticket, TicketFeedback $feedback): string
    {
        $ticket->loadMissing($this->ticketRelationships());

        $path = 'reports/incident-resolution/'.$ticket->ticket_number.'-feedback-'.$feedback->getKey().'.pdf';

        Storage::disk('local')->makeDirectory('reports/incident-resolution');
        Storage::disk('local')->put($path, $this->renderPdf($this->reportData($ticket, $feedback)));

        return $path;
    }

    /**
     * @return list<string>
     */
    private function ticketRelationships(): array
    {
        return [
            'system',
            'designation',
            'facility',
            'priorityLevel',
            'assignedStaff',
            'feedback',
            'statusLogs.changedBy',
            'statusLogs.newStatus',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Ticket $ticket, ?TicketFeedback $feedback): array
    {
        $completionLog = $ticket->statusLogs
            ->filter(fn (TicketStatusLog $log) => in_array($log->newStatus?->code, ['resolved', 'closed'], true))
            ->sortByDesc('created_at')
            ->first();

        $resolvedAt = $ticket->closed_at ?? $ticket->resolved_at;
        $ratingValues = collect([
            $feedback?->timeliness_rating,
            $feedback?->completeness_rating,
            $feedback?->overall_satisfaction_rating,
        ])->filter(fn ($value) => $value !== null);

        return [
            'logo_path' => public_path('coa2.png'),
            'ticket_number' => $ticket->ticket_number,
            'date_reported' => $ticket->created_at?->format('d/m/Y') ?? 'N/A',
            'date_resolved' => $resolvedAt?->format('d/m/Y') ?? 'N/A',
            'reported_by' => trim($ticket->full_name.($ticket->designation?->name ? ', '.$ticket->designation->name : '')),
            'facility' => $ticket->facility?->name ?? $ticket->district_name ?? 'N/A',
            'system_affected' => $ticket->system?->name ?? 'N/A',
            'severity' => $ticket->priorityLevel?->name ?? 'N/A',
            'resolved_by' => $ticket->assignedStaff?->name ?? $completionLog?->changedBy?->name ?? 'N/A',
            'turnaround_time' => $resolvedAt && $ticket->created_at
                ? CarbonInterval::instance($resolvedAt->diff($ticket->created_at))->cascade()->forHumans(short: true)
                : 'N/A',
            'incident_description' => $this->textOrFallback($ticket->description, 'No incident description recorded.'),
            'services_disrupted' => $this->textOrFallback($ticket->services_disrupted, 'Not recorded.'),
            'data_loss_risk' => $ticket->data_loss_risk ? ucfirst($ticket->data_loss_risk) : 'Not recorded.',
            'root_cause_analysis' => $this->textOrFallback($ticket->root_cause_analysis, 'Not recorded.'),
            'resolution_steps' => $this->resolutionSteps($ticket->work_done),
            'verification_testing' => $this->textOrFallback($ticket->verification_testing, 'Not recorded.'),
            'recommendations' => $this->textOrFallback($ticket->recommendations, 'Not recorded.'),
            'timeliness_rating' => $feedback ? $feedback->timeliness_rating.'/5' : 'Not yet submitted',
            'completeness_rating' => $feedback ? $feedback->completeness_rating.'/5' : 'Not yet submitted',
            'overall_rating' => $feedback ? $feedback->overall_satisfaction_rating.'/5' : 'Not yet submitted',
            'average_rating' => $ratingValues->isNotEmpty() ? round($ratingValues->avg(), 1).'/5' : 'Not yet submitted',
            'feedback_comments' => $this->textOrFallback($feedback?->comments, 'No customer comments submitted.'),
            'reviewed_by' => $completionLog?->changedBy?->name ?? '________________',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderPdf(array $data): string
    {
        return Pdf::loadView('reports.incident-resolution-report', $data)
            ->setPaper('a4')
            ->output();
    }

    private function resolutionSteps(?string $text): string
    {
        $clean = trim((string) $text);

        if ($clean === '') {
            return '1. No resolution steps recorded.';
        }

        $parts = preg_split('/\r\n|\r|\n/', $clean) ?: [$clean];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($part) => $part !== ''));

        if ($parts === []) {
            return '1. '.$clean;
        }

        return collect($parts)->values()->map(fn (string $step, int $index) => ($index + 1).'. '.$step)->implode("\n");
    }

    private function textOrFallback(?string $text, string $fallback): string
    {
        $clean = trim((string) $text);

        return $clean !== '' ? preg_replace('/\s+/', ' ', $clean) ?? $clean : $fallback;
    }
}
