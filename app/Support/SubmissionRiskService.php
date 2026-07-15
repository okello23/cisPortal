<?php

namespace App\Support;

class SubmissionRiskService
{
    public function score(array $context): array
    {
        $score = 0;
        $reasons = [];

        $add = function (string $rule, int $value) use (&$score, &$reasons): void {
            $score += $value;
            $reasons[] = ['rule' => $rule, 'score' => $value];
        };

        if (! empty($context['honeypot_filled'])) {
            $add('HONEYPOT_COMPLETED', 100);
        }

        if (! empty($context['turnstile_failed'])) {
            $add('TURNSTILE_FAILED', 100);
        }

        if (! empty($context['rate_limit_exceeded'])) {
            $add('RATE_LIMIT_EXCEEDED', 80);
        }

        if (! empty($context['fast_submission'])) {
            $add('FAST_SUBMISSION', 30);
        }

        if (! empty($context['disposable_email'])) {
            $add('DISPOSABLE_EMAIL', 20);
        }

        if (($context['url_count'] ?? 0) > 3) {
            $add('MULTIPLE_URLS', 25);
        }

        if (! empty($context['duplicate_exact'])) {
            $add('REPEATED_IDENTICAL_SUBMISSION', 40);
        } elseif (! empty($context['duplicate_possible'])) {
            $add('HIGHLY_SIMILAR_RECENT_SUBMISSION', 25);
        }

        if (! empty($context['invalid_facility_region'])) {
            $add('INVALID_FACILITY_REGION_COMBINATION', 40);
        }

        if (! empty($context['suspicious_attachment'])) {
            $add('SUSPICIOUS_ATTACHMENT', 100);
        }

        if (! empty($context['repeated_characters'])) {
            $add('REPEATED_CHARACTERS', 20);
        }

        if (! empty($context['trusted_domain'])) {
            $add('TRUSTED_DOMAIN', -10);
        }

        $level = 'LOW';
        if ($score >= config('cis_submission.risk_thresholds.critical')) {
            $level = 'CRITICAL';
        } elseif ($score >= config('cis_submission.risk_thresholds.high')) {
            $level = 'HIGH';
        } elseif ($score >= config('cis_submission.risk_thresholds.medium')) {
            $level = 'MEDIUM';
        }

        return [
            'score' => max(0, $score),
            'level' => $level,
            'reasons' => $reasons,
        ];
    }
}
