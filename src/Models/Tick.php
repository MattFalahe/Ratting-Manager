<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class Tick extends Model
{
    protected $table = 'ratting_manager_ticks';

    protected $fillable = [
        'character_id',
        'corporation_id',
        'solar_system_id',
        'ticked_at',
        'kind',
        'character_journal_id',
        'corporation_journal_id',
        'net_amount',
        'corp_tax_amount',
        'net_is_estimated',
        'tax_rate',
        'kill_group',
        'npc_kills',
        'session_id',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'solar_system_id' => 'integer',
        'ticked_at' => 'datetime',
        'net_amount' => 'float',
        'corp_tax_amount' => 'float',
        'net_is_estimated' => 'boolean',
        'tax_rate' => 'float',
        'npc_kills' => 'integer',
        'session_id' => 'integer',
    ];

    public const KIND_PAIRED = 'paired';
    public const KIND_CHARACTER = 'character';
    public const KIND_CORPORATION = 'corporation';

    public function kills()
    {
        return $this->hasMany(TickKill::class, 'tick_id');
    }
}
