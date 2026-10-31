<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class RattingSession extends Model
{
    protected $table = 'ratting_manager_sessions';

    protected $fillable = [
        'character_id',
        'corporation_id',
        'started_at',
        'ended_at',
        'minutes',
        'tick_count',
        'primary_system_id',
        'system_count',
        'net_amount',
        'estimated_amount',
        'npc_kills',
        'isk_per_hour',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minutes' => 'integer',
        'tick_count' => 'integer',
        'primary_system_id' => 'integer',
        'system_count' => 'integer',
        'net_amount' => 'float',
        'estimated_amount' => 'float',
        'npc_kills' => 'integer',
        'isk_per_hour' => 'float',
    ];
}
