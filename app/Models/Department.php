<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];
}
