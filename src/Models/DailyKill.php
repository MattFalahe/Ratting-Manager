<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class DailyKill extends Model
{
    protected $table = 'ratting_manager_daily_kills';

    protected $fillable = [
        'date',
        'corporation_id',
        'solar_system_id',
        'npc_type_id',
        'quantity',
        'characters',
    ];

    protected $casts = [
        'date' => 'date',
        'corporation_id' => 'integer',
        'solar_system_id' => 'integer',
        'npc_type_id' => 'integer',
        'quantity' => 'integer',
        'characters' => 'integer',
    ];
}
