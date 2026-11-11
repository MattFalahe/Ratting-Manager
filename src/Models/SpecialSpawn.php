<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialSpawn extends Model
{
    protected $table = 'ratting_manager_special_spawns';

    protected $fillable = [
        'kill_group',
        'npc_type_id',
        'quantity',
        'tick_id',
        'character_id',
        'corporation_id',
        'solar_system_id',
        'ticked_at',
        'notified_at',
    ];

    protected $casts = [
        'npc_type_id' => 'integer',
        'quantity' => 'integer',
        'tick_id' => 'integer',
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'solar_system_id' => 'integer',
        'ticked_at' => 'datetime',
        'notified_at' => 'datetime',
    ];
}
