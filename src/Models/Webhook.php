<?php

namespace RattingManager\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Webhook extends Model
{
    protected $table = 'ratting_manager_webhooks';

    protected $fillable = [
        'description',
        'webhook_url',
        'corporation_id',
        'enabled',
    ];

    protected $hidden = ['webhook_url'];

    protected $casts = [
        'corporation_id' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * Kept encrypted with SeAT's APP_KEY: the token in the URL lets anyone post
     * to the channel, so it should not sit readable in the table or in a dump
     * of it.
     *
     * A row still in plain text reads as it is, in case the code gets here
     * before the migration has. One that can no longer be decrypted, because
     * APP_KEY changed or the dump came from another server, reads as empty: it
     * fails the URL check before anything is sent, and Settings asks for the
     * URL again instead of a page breaking.
     */
    protected function webhookUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '' || str_starts_with((string) $value, 'https://')) {
                    return $value;
                }

                try {
                    return Crypt::decryptString((string) $value);
                } catch (DecryptException $e) {
                    return '';
                }
            },
            set: fn ($value) => $value === null || $value === '' ? $value : Crypt::encryptString((string) $value)
        );
    }

    public function types()
    {
        return $this->belongsToMany(NotificationType::class, 'ratting_manager_type_webhook', 'webhook_id', 'type_id')
            ->withPivot(['id', 'enabled', 'role_mention', 'role_source', 'role_id'])
            ->withTimestamps();
    }

    /** Slack takes plain text; everything else gets Discord's embeds. */
    public function isSlack(): bool
    {
        return str_contains((string) $this->webhook_url, 'hooks.slack.com');
    }

    /** Whether the stored URL could not be read, so an admin has to enter it again. */
    public function urlLost(): bool
    {
        return (string) $this->webhook_url === '';
    }

    /** The URL with its token cut, for pages and logs. */
    public function maskedUrl(): string
    {
        $url = (string) $this->webhook_url;

        return preg_replace('#/([^/]{6})[^/]+$#', '/$1...', $url) ?? $url;
    }
}
