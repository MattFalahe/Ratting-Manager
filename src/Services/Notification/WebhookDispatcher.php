<?php

namespace RattingManager\Services\Notification;

use Illuminate\Support\Facades\Log;
use RattingManager\Models\NotificationType;
use RattingManager\Models\Webhook;

/**
 * Which webhooks a notification goes to, and who it mentions there. The one
 * place every send goes through, laid out as Structure Manager's.
 *
 * A notification goes to every enabled webhook bound to its type, when the
 * type is switched on, and the webhook is for every corporation or for the
 * one the notification is about. The mention is picked in two steps:
 *
 *   1. the binding's own role, set for this type on this webhook
 *   2. the type's default role
 *
 * An empty step means "not set" and falls through. "none" stops there and
 * mentions nobody, so one webhook can stay quiet for a type that pings
 * everywhere else.
 */
class WebhookDispatcher
{
    public const NO_MENTION = 'none';

    public static function isNoMention(?string $value): bool
    {
        return $value !== null && strtolower(trim($value)) === self::NO_MENTION;
    }

    public function isTypeEnabled(string $typeKey): bool
    {
        return (bool) NotificationType::where('type_key', $typeKey)->value('enabled');
    }

    /**
     * @return array<int, array{webhook: Webhook, role_mention: ?string}>
     */
    public function bindings(string $typeKey, ?int $corporationId): array
    {
        $type = NotificationType::where('type_key', $typeKey)->first();
        if ($type === null || !$type->enabled) {
            return [];
        }

        $webhooks = $type->webhooks()
            ->wherePivot('enabled', true)
            ->where('ratting_manager_webhooks.enabled', true)
            // A webhook for one corporation never gets another's news. A
            // notification about no corporation in particular goes only to
            // webhooks for every corporation.
            ->where(function ($q) use ($corporationId) {
                $q->whereNull('ratting_manager_webhooks.corporation_id');
                if ($corporationId !== null && $corporationId > 0) {
                    $q->orWhere('ratting_manager_webhooks.corporation_id', $corporationId);
                }
            })
            ->get();

        $bindings = [];
        foreach ($webhooks as $webhook) {
            $mention = $webhook->pivot->role_mention ?: ($type->role_mention ?: null);

            $bindings[] = [
                'webhook' => $webhook,
                'role_mention' => self::isNoMention($mention) ? null : $mention,
            ];
        }

        return $bindings;
    }

    /**
     * A stored mention as Discord wants it: the text to put in the message,
     * and the allowed_mentions block that lets exactly that role or user be
     * pinged and nobody else.
     *
     * Takes <@&ROLE_ID>, <@USER_ID>, <@!USER_ID> or a bare role ID.
     *
     * @return array{0: string, 1: array}
     */
    public static function formatMention(?string $raw): array
    {
        $none = ['parse' => [], 'users' => [], 'roles' => []];

        if ($raw === null || trim($raw) === '' || self::isNoMention($raw)) {
            return ['', $none];
        }

        $mention = trim($raw);

        if (preg_match('/^<@&(\d+)>$/', $mention, $m) || preg_match('/^(\d+)$/', $mention, $m)) {
            return ["<@&{$m[1]}>", ['parse' => [], 'users' => [], 'roles' => [$m[1]]]];
        }
        if (preg_match('/^<@!?(\d+)>$/', $mention, $m)) {
            return ["<@{$m[1]}>", ['parse' => [], 'users' => [$m[1]], 'roles' => []]];
        }

        // Anything else could carry @everyone or crafted markup, so it is
        // dropped, and logged so an admin can see why nobody was pinged.
        Log::warning('Ratting Manager: ignoring a role mention that is not <@&ROLE_ID>, <@USER_ID> or a role ID: "' . $mention . '"');

        return ['', $none];
    }
}
