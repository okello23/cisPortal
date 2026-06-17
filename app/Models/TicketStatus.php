<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    protected $fillable = ['name', 'code', 'color', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];
}
