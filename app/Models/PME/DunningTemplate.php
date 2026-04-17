<?php

namespace App\Models\PME;

use Illuminate\Database\Eloquent\Model;

class DunningTemplate extends Model
{
    protected $fillable = ['day_offset', 'body', 'active'];

    protected $casts = [
        'day_offset' => 'integer',
        'active' => 'boolean',
    ];
}
