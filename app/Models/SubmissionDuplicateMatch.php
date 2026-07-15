<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDuplicateMatch extends Model
{
    protected $fillable = [
        'ticket_id',
        'matched_ticket_id',
        'confidence',
        'review_status',
        'reasons',
    ];

    protected $casts = [
        'reasons' => 'array',
    ];
}
