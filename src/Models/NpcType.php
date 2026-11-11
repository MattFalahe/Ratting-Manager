<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class NpcType extends Model
{
    protected $table = 'ratting_manager_npc_types';

    protected $primaryKey = 'type_id';

    public $incrementing = false;

    protected $fillable = [
        'type_id',
        'name',
        'group_id',
        'group_name',
        'faction',
        'size',
        'rank',
        'mass',
        'classified_by',
    ];

    protected $casts = [
        'type_id' => 'integer',
        'group_id' => 'integer',
        'mass' => 'float',
    ];
}
