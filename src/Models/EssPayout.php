<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class EssPayout extends Model
{
    protected $table = 'ratting_manager_ess_payouts';

    protected $fillable = [
        'character_id',
        'corporation_id',
        'solar_system_id',
        'paid_at',
        'kind',
        'character_journal_id',
        'corporation_journal_id',
        'net_amount',
        'corp_tax_amount',
        'net_is_estimated',
        'tax_rate',
    ];

    protected $casts = [
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'solar_system_id' => 'integer',
        'paid_at' => 'datetime',
        'net_amount' => 'float',
        'corp_tax_amount' => 'float',
        'net_is_estimated' => 'boolean',
        'tax_rate' => 'float',
    ];

    public const KIND_PAIRED = 'paired';
    public const KIND_CHARACTER = 'character';
    public const KIND_CORPORATION = 'corporation';
}
