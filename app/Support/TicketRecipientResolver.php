<?php

namespace App\Support;

use App\Models\User;

class TicketRecipientResolver
{
    public function newTicketAlertEmails(): array
    {
        return collect($this->emailsForRoles([
            User::ROLE_ICT_MANAGER,
            User::ROLE_ICT_ADMIN,
        ]))
            ->push(config('mail.ict_support_address'))
            ->filter()
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values()
            ->all();
    }

    public function escalationCcEmails(?string $excludeEmail = null): array
    {
        return $this->emailsForRoles([User::ROLE_ICT_MANAGER], [$excludeEmail]);
    }

    public function reminderCcEmails(?string $excludeEmail = null): array
    {
        return $this->emailsForRoles([User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR], [$excludeEmail]);
    }

    private function emailsForRoles(array $roles, array $exclude = []): array
    {
        $excluded = collect($exclude)->filter()->values()->all();

        return User::query()
            ->where('active', true)
            ->whereIn('role', $roles)
            ->whereNotNull('email')
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('email', $excluded))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
