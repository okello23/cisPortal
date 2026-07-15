<?php

namespace App\Support;

use App\Models\SubmissionBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubmissionRateLimitService
{
    public function ensureAllowed(Request $request, ?string $email = null): array
    {
        $ip = (string) $request->ip();
        $normalizedEmail = filled($email) ? mb_strtolower(trim($email)) : null;

        foreach ([
            ['ip', $ip],
            ['email', $normalizedEmail],
            ['domain', $normalizedEmail ? substr(strrchr($normalizedEmail, '@') ?: '', 1) : null],
        ] as [$type, $value]) {
            if ($value && $this->isBlocked($type, $value)) {
                return ['allowed' => false, 'reason' => 'blocked', 'scope' => $type];
            }
        }

        if (! $this->hitWithinLimit('global:1m', $this->rateLimit('global_per_minute', 100), 60)) {
            return ['allowed' => false, 'reason' => 'global_rate_limit', 'scope' => 'global'];
        }

        $trusted = $this->isTrustedIp($ip);
        $ipLimit = $trusted
            ? $this->rateLimit('trusted_per_ip_10_minutes', $this->rateLimit('per_ip_10_minutes', 5))
            : $this->rateLimit('per_ip_10_minutes', 5);

        if (! $this->hitWithinLimit('ip:'.$ip.':10m', $ipLimit, 600)) {
            $this->temporarilyBlock('ip', $ip, 'Rate limit exceeded');

            return ['allowed' => false, 'reason' => 'ip_rate_limit', 'scope' => 'ip'];
        }

        if ($normalizedEmail && ! $this->hitWithinLimit('email:'.$normalizedEmail.':60m', $this->rateLimit('per_email_60_minutes', 10), 3600)) {
            $this->temporarilyBlock('email', $normalizedEmail, 'Rate limit exceeded');

            return ['allowed' => false, 'reason' => 'email_rate_limit', 'scope' => 'email'];
        }

        return ['allowed' => true, 'reason' => null, 'scope' => null];
    }

    public function clearTemporaryBlock(string $type, string $value): void
    {
        SubmissionBlock::query()
            ->where('block_type', $type)
            ->where('block_value', $value)
            ->update(['is_active' => false]);
    }

    private function hitWithinLimit(string $key, int $limit, int $ttlSeconds): bool
    {
        $cacheKey = 'submission-rate-limit:'.$key;
        $count = Cache::get($cacheKey, 0);

        if ($count >= $limit) {
            return false;
        }

        Cache::put($cacheKey, $count + 1, now()->addSeconds($ttlSeconds));

        return true;
    }

    private function temporarilyBlock(string $type, string $value, string $reason): void
    {
        SubmissionBlock::query()->updateOrCreate(
            ['block_type' => $type, 'block_value' => $value, 'is_active' => true],
            [
                'reason' => $reason,
                'blocked_at' => now(),
                'expires_at' => now()->addMinutes($this->rateLimit('block_minutes', 30)),
            ]
        );
    }

    private function rateLimit(string $key, int $default): int
    {
        $value = config('cis_submission.rate_limits.'.$key);

        if (is_int($value)) {
            return max(1, $value);
        }

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        return $default;
    }

    private function isBlocked(string $type, string $value): bool
    {
        SubmissionBlock::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        return SubmissionBlock::query()
            ->where('block_type', $type)
            ->where('block_value', $value)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    private function isTrustedIp(string $ip): bool
    {
        foreach (config('cis_submission.trusted_networks', []) as $trustedIp) {
            if ($trustedIp === $ip) {
                return true;
            }
        }

        return false;
    }
}
