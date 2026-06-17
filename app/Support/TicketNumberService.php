<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TicketNumberService
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $prefix = 'CIS-'.Carbon::now()->format('ymd').'-';
            $latest = Ticket::query()
                ->where('ticket_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->latest('id')
                ->value('ticket_number');

            $sequence = $latest ? ((int) substr($latest, -3)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        });
    }
}
