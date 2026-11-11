<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class TickKill extends Model
{
    protected $table = 'ratting_manager_tick_kills';

    public $timestamps = false;

    protected $fillable = [
        'tick_id',
        'npc_type_id',
        'quantity',
    ];

    protected $casts = [
        'tick_id' => 'integer',
        'npc_type_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function npcType()
    {
        return $this->belongsTo(NpcType::class, 'npc_type_id', 'type_id');
    }
}
