<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RattingManager\Models\NotificationType;
use RattingManager\Models\Webhook;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Notification\DiscordRoleResolver;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Notification\NotificationTypes;
use RattingManager\Services\Notification\RoutingMap;
use RattingManager\Services\Notification\WebhookDeliveryService;
use RattingManager\Services\Npc\NpcRegistry;
use RattingManager\Services\Universe\HomeSpace;
use Seat\Web\Http\Controllers\Controller;

/**
 * Webhooks, what each notification type sends to, and the alert
 * thresholds. Nothing is bound on install: an admin picks what each webhook
 * carries.
 */
class NotificationSettingsController extends Controller
{
    /** What a stored mention may be: none, a role or user mention, or a bare role ID. */
    private const MENTION_RULE = 'regex:/^(none|<@[&!]?\d+>|\d+)$/i';

    public function store(Request $request)
    {
        $data = $this->validateWebhook($request);
        NotificationTypes::ensureRows();

        $webhook = Webhook::create([
            'description' => $data['description'],
            'webhook_url' => trim($data['webhook_url']),
            'corporation_id' => ($data['corporation_id'] ?? null) ?: null,
            'enabled' => $request->boolean('enabled'),
        ]);

        // The types ticked on the form are bound now, by the admin's choice.
        foreach (NotificationType::whereIn('type_key', $data['types'] ?? [])->get() as $type) {
            $type->webhooks()->syncWithoutDetaching([$webhook->id => ['enabled' => true]]);
        }

        return $this->back('webhooks', trans('ratting-manager::notification_settings.webhook_added', ['name' => $webhook->description]));
    }

    public function edit(Request $request, int $webhook)
    {
        $model = Webhook::findOrFail($webhook);
        // The URL is never sent back to the page, so left empty it stays as it is.
        $data = $this->validateWebhook($request, !$model->urlLost());
        NotificationTypes::ensureRows();

        DB::transaction(function () use ($model, $data, $request) {
            $url = trim((string) ($data['webhook_url'] ?? ''));

            $model->update(($url === '' ? [] : ['webhook_url' => $url]) + [
                'description' => $data['description'],
                'corporation_id' => ($data['corporation_id'] ?? null) ?: null,
                'enabled' => $request->boolean('enabled'),
            ]);

            $wanted = $data['types'] ?? [];
            $existing = $model->types()->get()->keyBy('id');
            foreach (NotificationType::all() as $type) {
                $on = in_array($type->type_key, $wanted, true);

                if (isset($existing[$type->id])) {
                    // Unticking keeps the binding, switched off, so its role is not lost.
                    $model->types()->updateExistingPivot($type->id, ['enabled' => $on]);
                } elseif ($on) {
                    $model->types()->attach($type->id, ['enabled' => true]);
                }
            }
        });

        return $this->back('webhooks', trans('ratting-manager::notification_settings.webhook_saved', ['name' => $model->description]));
    }

    public function destroy(int $webhook)
    {
        $model = Webhook::findOrFail($webhook);
        $model->types()->detach();
        $model->delete();

        return $this->back('webhooks', trans('ratting-manager::notification_settings.webhook_deleted', ['name' => $model->description]));
    }

    public function test(int $webhook, WebhookDeliveryService $delivery)
    {
        $model = Webhook::findOrFail($webhook);
        $bound = RoutingMap::boundTypes()[$model->id] ?? [];

        $message = Message::make(trans('ratting-manager::notification_settings.test_title'), 0x667EEA)
            ->description(trans('ratting-manager::notification_settings.test_body'))
            ->field(trans('ratting-manager::notification_settings.webhook'), $model->description ?: '#' . $model->id)
            ->field(trans('ratting-manager::notification_settings.bound_types'), $bound ? implode("\n", array_map([NotificationTypes::class, 'name'], $bound)) : trans('ratting-manager::notification_settings.nothing_bound'), false);

        $ok = $delivery->send($model, $message, null, 'webhook_test');

        return $ok
            ? $this->back('webhooks', trans('ratting-manager::notification_settings.test_sent', ['name' => $model->description]))
            : $this->back('webhooks', trans('ratting-manager::notification_settings.test_failed', ['name' => $model->description, 'error' => $this->lastError($model->id)]), 'error');
    }

    /**
     * The type switches, default roles, which webhook each type goes to with
     * its own role, and the alert thresholds, all in one form.
     */
    public function update(Request $request, Settings $settings, HomeSpace $homeSpace)
    {
        $data = $request->validate([
            'types' => ['array'],
            'types.*.role' => ['nullable', 'string', 'max:100', self::MENTION_RULE],
            'bind' => ['array'],
            'bind.*.*.role' => ['nullable', 'string', 'max:100', self::MENTION_RULE],
            'spawn_min_rank' => ['required', Rule::in(array_slice(NpcRegistry::RANKS, 1))],
            'spawn_max_age_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'stalled_hours' => ['required', 'integer', 'min:2', 'max:168'],
            'outsider_min_kills' => ['required', 'integer', 'min:10', 'max:10000'],
            'home_regions' => ['nullable', 'string', 'max:2000'],
            'home_constellations' => ['nullable', 'string', 'max:2000'],
            'home_systems' => ['nullable', 'string', 'max:4000'],
            'ship_lost_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'rankings_size' => ['required', 'integer', 'min:3', 'max:25'],
        ], [
            'regex' => trans('ratting-manager::notification_settings.mention_invalid'),
        ]);

        NotificationTypes::ensureRows();
        $lookup = DiscordRoleResolver::lookup();
        $webhookIds = Webhook::pluck('id')->all();

        DB::transaction(function () use ($request, $data, $lookup, $webhookIds) {
            foreach (NotificationType::all() as $type) {
                $key = $type->type_key;
                $type->update(['enabled' => $request->boolean("types.{$key}.enabled")] + $this->role($data['types'][$key]['role'] ?? null, $lookup));

                $existing = $type->webhooks()->get()->keyBy('id');
                foreach ($webhookIds as $id) {
                    $on = $request->boolean("bind.{$key}.{$id}.on");
                    $role = $this->role($data['bind'][$key][$id]['role'] ?? null, $lookup);

                    if (isset($existing[$id])) {
                        // Unticking keeps the binding, switched off, so its role is not lost.
                        $type->webhooks()->updateExistingPivot($id, ['enabled' => $on] + $role);
                    } elseif ($on) {
                        $type->webhooks()->attach($id, ['enabled' => true] + $role);
                    }
                }
            }
        });

        $settings->set('notifications.spawn_min_rank', $data['spawn_min_rank']);
        $settings->set('notifications.spawn_capitals', $request->boolean('spawn_capitals'));
        $settings->set('notifications.spawn_max_age_hours', $data['spawn_max_age_hours']);
        $settings->set('notifications.stalled_hours', $data['stalled_hours']);
        $settings->set('notifications.outsider_min_kills', $data['outsider_min_kills']);
        $settings->set('notifications.ship_lost_minutes', $data['ship_lost_minutes']);
        $settings->set('notifications.rankings_size', $data['rankings_size']);

        // The home space by name or ID. Anything SeAT does not know is left
        // out, and the admin is told which, so a typo never passes as saved.
        $unknown = [];
        foreach (HomeSpace::KINDS as $kind => [$key]) {
            [$ids, $missed] = $homeSpace->parse($kind, $data['home_' . $kind] ?? null);
            $settings->set($key, $ids);
            $unknown = array_merge($unknown, $missed);
        }

        $response = $this->back('notifications', trans('ratting-manager::notification_settings.saved'));

        return $unknown === []
            ? $response
            : $response->with('error', trans('ratting-manager::notification_settings.home_space_unknown', ['names' => implode(', ', $unknown)]));
    }

    /** A mention as stored: the text, and where the role came from when a provider knows it. */
    private function role(?string $raw, array $lookup): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return ['role_mention' => null, 'role_source' => null, 'role_id' => null];
        }

        $describe = DiscordRoleResolver::describe($raw, $lookup);
        if ($describe['kind'] === 'none') {
            return ['role_mention' => 'none', 'role_source' => null, 'role_id' => null];
        }

        return [
            'role_mention' => $raw,
            'role_source' => $describe['known'] ? ($lookup[$describe['id']]['source'] ?? 'manual') : 'manual',
            'role_id' => $describe['id'],
        ];
    }

    private function validateWebhook(Request $request, bool $keepUrl = false): array
    {
        $configured = app(Settings::class)->configuredCorporations();

        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'webhook_url' => [$keepUrl ? 'nullable' : 'required', 'string', 'max:500', function ($attribute, $value, $fail) {
                if (!WebhookDeliveryService::isValidUrl(trim($value))) {
                    $fail(trans('ratting-manager::notification_settings.url_invalid'));
                }
            }],
            'corporation_id' => ['nullable', 'integer', Rule::in($configured)],
            'types' => ['array'],
            'types.*' => [Rule::in(NotificationTypes::keys())],
        ]);
    }

    private function lastError(int $webhookId): string
    {
        $row = DB::table('ratting_manager_webhook_deliveries')->where('webhook_id', $webhookId)->orderByDesc('id')->first(['error_message']);

        return $row && $row->error_message ? mb_substr($row->error_message, 0, 200) : '-';
    }

    private function back(string $section, string $message, string $level = 'success')
    {
        return redirect()->to(route('ratting-manager.settings.index') . '#' . $section)->with($level, $message);
    }
}
