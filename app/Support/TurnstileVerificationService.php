<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class TurnstileVerificationService
{
    public function verify(?string $token, ?string $ipAddress = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => true, 'error_code' => null];
        }

        if (! filled($token)) {
            return ['success' => false, 'error_code' => 'missing-input-response'];
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('cis_submission.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ipAddress,
            ]);

        if (! $response->ok()) {
            return ['success' => false, 'error_code' => 'http-error'];
        }

        $payload = $response->json();

        return [
            'success' => (bool) ($payload['success'] ?? false),
            'error_code' => $payload['error-codes'][0] ?? null,
        ];
    }

    private function isConfigured(): bool
    {
        return (bool) config('cis_submission.turnstile.enabled')
            && filled(config('cis_submission.turnstile.site_key'))
            && filled(config('cis_submission.turnstile.secret_key'));
    }
}
