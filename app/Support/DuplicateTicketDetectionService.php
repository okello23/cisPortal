<?php

namespace App\Support;

use App\Models\Ticket;

class DuplicateTicketDetectionService
{
    public function fingerprint(array $attributes): string
    {
        $normalized = implode('|', [
            mb_strtolower(trim((string) ($attributes['email'] ?? ''))),
            (string) ($attributes['facility_id'] ?? ''),
            (string) ($attributes['system_id'] ?? ''),
            $this->normalizeText((string) ($attributes['description'] ?? '')),
        ]);

        return hash('sha256', $normalized);
    }

    public function detect(array $attributes): array
    {
        $fingerprint = $this->fingerprint($attributes);
        $windowStart = now()->subHours(config('cis_submission.duplicate_check_hours'));

        $recentTickets = Ticket::query()
            ->where('created_at', '>=', $windowStart)
            ->where(function ($query) use ($attributes, $fingerprint) {
                $query->where('content_fingerprint', $fingerprint)
                    ->orWhere(function ($emailQuery) use ($attributes) {
                        $emailQuery->where('email', $attributes['email'] ?? null)
                            ->where('facility_id', $attributes['facility_id'] ?? null)
                            ->where('system_id', $attributes['system_id'] ?? null);
                    });
            })
            ->latest()
            ->get();

        $exact = $recentTickets->firstWhere('content_fingerprint', $fingerprint);
        $possible = $recentTickets->first();

        return [
            'fingerprint' => $fingerprint,
            'exact_duplicate' => $exact,
            'possible_duplicate' => $possible,
        ];
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim(mb_strtolower($value))) ?? '';

        return strip_tags($value);
    }
}
