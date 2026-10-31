<?php

namespace RattingManager\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
    protected $table = 'ratting_manager_notification_types';

    protected $fillable = [
        'type_key',
        'enabled',
        'role_mention',
        'role_source',
        'role_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function webhooks()
    {
        return $this->belongsToMany(Webhook::class, 'ratting_manager_type_webhook', 'type_id', 'webhook_id')
            ->withPivot(['id', 'enabled', 'role_mention', 'role_source', 'role_id'])
            ->withTimestamps();
    }
}
