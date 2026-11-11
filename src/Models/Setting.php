<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'ratting_manager_settings';

    protected $fillable = [
        'key',
        'corporation_id',
        'value',
    ];

    protected $casts = [
        'corporation_id' => 'integer',
    ];
}
