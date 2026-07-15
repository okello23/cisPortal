<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketStatusLog;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Storage;

class IncidentResolutionReportService
{
    public function generateForFeedback(Ticket $ticket, TicketFeedback $feedback): string
    {
        $ticket->loadMissing([
            'system',
            'designation',
            'facility',
            'priorityLevel',
            'assignedStaff',
            'resolutionCategory',
            'statusLogs.changedBy',
            'statusLogs.newStatus',
        ]);

        $path = 'reports/incident-resolution/'.$ticket->ticket_number.'-feedback-'.$feedback->getKey().'.pdf';

        Storage::disk('local')->makeDirectory('reports/incident-resolution');
        Storage::disk('local')->put($path, $this->toPdf($this->reportLines($ticket, $feedback)));

        return $path;
    }

    /**
     * @return list<string>
     */
    private function reportLines(Ticket $ticket, TicketFeedback $feedback): array
    {
        $completionLog = $ticket->statusLogs
            ->filter(fn (TicketStatusLog $log) => in_array($log->newStatus?->code, ['resolved', 'closed'], true))
            ->sortByDesc('created_at')
            ->first();

        $resolvedAt = $ticket->closed_at ?? $ticket->resolved_at;
        $averageRating = round(collect([
            $feedback->timeliness_rating,
            $feedback->completeness_rating,
            $feedback->overall_satisfaction_rating,
        ])->avg() ?? 0, 1);

        $turnaround = $resolvedAt && $ticket->created_at
            ? CarbonInterval::instance($resolvedAt->diff($ticket->created_at))->cascade()->forHumans(short: true)
            : 'N/A';

        return [
            'CENTRAL PUBLIC HEALTH LABORATORIES (CPHL)',
            'Ministry of Health, Republic of Uganda - ICT Support Unit',
            '',
            'ICT INCIDENT RESOLUTION REPORT',
            '',
            '1. Ticket / Incident Reference',
            'Ticket / Reference No.: '.$ticket->ticket_number,
            'Date Reported: '.($ticket->created_at?->format('d/m/Y') ?? 'N/A'),
            'Date Resolved: '.($resolvedAt?->format('d/m/Y') ?? 'N/A'),
            'Reported By: '.trim($ticket->full_name.' - '.($ticket->designation?->name ?? 'N/A')),
            'Facility: '.($ticket->facility?->name ?? $ticket->district_name ?? 'N/A'),
            'System Affected: '.($ticket->system?->name ?? 'N/A'),
            'Severity: '.($ticket->priorityLevel?->name ?? 'N/A'),
            'Resolved By: '.($ticket->assignedStaff?->name ?? $completionLog?->changedBy?->name ?? 'N/A'),
            'Turnaround Time: '.$turnaround,
            '',
            '2. Incident Description',
            $this->textOrFallback($ticket->description, 'No incident description recorded.'),
            '',
            '3. Impact Assessment',
            'Services Disrupted: '.$this->textOrFallback($ticket->services_disrupted, 'Not recorded.'),
            'Data Loss Risk: '.($ticket->data_loss_risk ? ucfirst($ticket->data_loss_risk) : 'Not recorded.'),
            '',
            '4. Root Cause Analysis',
            $this->textOrFallback($ticket->root_cause_analysis, 'Not recorded.'),
            '',
            '5. Resolution Steps Taken',
            $this->textOrFallback($ticket->work_done, 'Not recorded.'),
            '',
            '6. Verification / Testing',
            $this->textOrFallback($ticket->verification_testing, 'Not recorded.'),
            '',
            '7. Recommendations / Preventive Measures',
            $this->textOrFallback($ticket->recommendations, 'Not recorded.'),
            '',
            '8. Customer Rating Summary',
            'Timeliness Rating: '.$feedback->timeliness_rating.'/5',
            'Completeness Rating: '.$feedback->completeness_rating.'/5',
            'Overall Satisfaction Rating: '.$feedback->overall_satisfaction_rating.'/5',
            'Average Rating: '.$averageRating.'/5',
            'Comments: '.$this->textOrFallback($feedback->comments, 'No customer comments submitted.'),
            '',
            '9. Sign-Off',
            'Resolved By: '.($ticket->assignedStaff?->name ?? $completionLog?->changedBy?->name ?? 'N/A'),
            'Reviewed By: '.($completionLog?->changedBy?->name ?? 'N/A'),
            'Approved By: ________________________________',
        ];
    }

    private function textOrFallback(?string $text, string $fallback): string
    {
        $clean = trim((string) $text);

        return $clean !== '' ? preg_replace('/\s+/', ' ', $clean) ?? $clean : $fallback;
    }

    /**
     * @param  list<string>  $lines
     */
    private function toPdf(array $lines): string
    {
        $wrappedLines = [];

        foreach ($lines as $line) {
            $segments = $this->wrapLine($line, 92);

            foreach ($segments as $segment) {
                $wrappedLines[] = $segment;
            }
        }

        $fontObject = 3;
        $perPage = 40;
        $lineHeight = 14;
        $pages = array_chunk($wrappedLines, $perPage);
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageObjectIds = [];
        $contentObjectIds = [];
        $objectId = 4;

        foreach ($pages as $index => $pageLines) {
            $content = "BT\n/F1 10 Tf\n40 800 Td\n";

            foreach ($pageLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $content .= "0 -{$lineHeight} Td\n";
                }

                $content .= '('.$this->escapePdfText($line).") Tj\n";
            }

            $content .= "ET";

            $contentObjectIds[$index] = $objectId++;
            $pageObjectIds[$index] = $objectId++;

            $objects[$contentObjectIds[$index]] = '<< /Length '.strlen($content).' >>'."\nstream\n".$content."\nendstream";
            $objects[$pageObjectIds[$index]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 '.$fontObject.' 0 R >> >> /Contents '.$contentObjectIds[$index].' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pageObjectIds).' /Kids ['.collect($pageObjectIds)->map(fn (int $id) => $id.' 0 R')->implode(' ').'] >>';
        $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));

        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private function wrapLine(string $line, int $length): array
    {
        if ($line === '') {
            return [''];
        }

        $wrapped = wordwrap($line, $length, "\n", true);

        return explode("\n", $wrapped);
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
