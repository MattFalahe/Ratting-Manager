<?php

namespace RattingManager\Services\Notification;

use Illuminate\Support\Facades\DB;
use RattingManager\Models\NotificationType;
use RattingManager\Models\Webhook;
use RattingManager\Services\Universe\HomeSpace;

/**
 * Where each notification type goes and who it mentions there, worked out
 * the way WebhookDispatcher works it out when it sends. For the Settings
 * page, so what is shown is what will happen.
 */
class RoutingMap
{
    /**
     * @return array<string, array{type: NotificationType, routes: array, blocked: ?string}>
     */
    public function build(?array $roleLookup = null): array
    {
        NotificationTypes::ensureRows();
        $roleLookup ??= DiscordRoleResolver::lookup();
        $corporationNames = DB::table('corporation_infos')->pluck('name', 'corporation_id');

        $types = NotificationType::with('webhooks')->get()->keyBy('type_key');
        $map = [];

        foreach (NotificationTypes::keys() as $key) {
            $type = $types[$key] ?? null;
            if ($type === null) {
                continue;
            }

            $blocked = $this->blocked($key);
            $routes = [];
            foreach ($type->webhooks->sortBy('description') as $webhook) {
                $own = $webhook->pivot->role_mention;
                $mention = $own ?: $type->role_mention;
                $tier = $own ? 'binding' : ($type->role_mention ? 'type' : null);

                $routes[] = [
                    'webhook' => $webhook,
                    'corporation' => $webhook->corporation_id ? ($corporationNames[$webhook->corporation_id] ?? 'Corporation #' . $webhook->corporation_id) : null,
                    'live' => $blocked === null && $type->enabled && $webhook->enabled && $webhook->pivot->enabled,
                    'binding_enabled' => (bool) $webhook->pivot->enabled,
                    'mention' => DiscordRoleResolver::describe($mention, $roleLookup),
                    'tier' => $tier,
                ];
            }

            $map[$key] = ['type' => $type, 'routes' => $routes, 'blocked' => $blocked];
        }

        return $map;
    }

    /** Why a type sends nothing whatever its switch and webhooks say, or null when nothing stops it. */
    private function blocked(string $key): ?string
    {
        if ($key === 'outsiders' && !app(HomeSpace::class)->isSet()) {
            return trans('ratting-manager::notification_settings.home_space_missing_body');
        }

        return null;
    }

    /**
     * What a webhook is bound to, as type keys.
     *
     * @return array<int, string[]> webhook id => type keys
     */
    public static function boundTypes(): array
    {
        return DB::table('ratting_manager_type_webhook as b')
            ->join('ratting_manager_notification_types as t', 't.id', '=', 'b.type_id')
            ->where('b.enabled', true)
            ->get(['b.webhook_id', 't.type_key'])
            ->groupBy('webhook_id')
            ->map(fn ($rows) => $rows->pluck('type_key')->all())
            ->all();
    }
}
