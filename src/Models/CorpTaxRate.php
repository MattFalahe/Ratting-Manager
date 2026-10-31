<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class CorpTaxRate extends Model
{
    protected $table = 'ratting_manager_corp_tax_rates';

    protected $fillable = [
        'corporation_id',
        'date',
        'tax_rate',
        'source',
        'paired_rows',
    ];

    protected $casts = [
        'corporation_id' => 'integer',
        'date' => 'date',
        'tax_rate' => 'float',
        'paired_rows' => 'integer',
    ];
}
