<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OutboundMail
{
    public function isConfiguredForDelivery(): bool
    {
        $mailer = config('mail.default');

        return ! in_array($mailer, ['log', 'array', null], true)
            && filled(config('mail.from.address'));
    }

    public function send(string $recipient, Mailable $message): bool
    {
        if (! $this->isConfiguredForDelivery()) {
            Log::warning('Email was not sent because a delivery mailer is not configured.', [
                'recipient' => $recipient,
                'mail_type' => class_basename($message),
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->send($message);

            return true;
        } catch (Throwable $exception) {
            Log::error('Email delivery failed.', [
                'recipient' => $recipient,
                'mail_type' => class_basename($message),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
