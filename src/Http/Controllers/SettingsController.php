<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RattingManager\Models\Webhook;
use RattingManager\Services\Configuration\CorporationDirectory;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Notification\DiscordRoleResolver;
use RattingManager\Services\Notification\NotificationTypes;
use RattingManager\Services\Notification\RoutingMap;
use RattingManager\Services\Notification\WebhookDeliveryService;
use RattingManager\Services\Ratting\RawTicks;
use RattingManager\Services\Universe\HomeSpace;
use Seat\Web\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index(Settings $settings, CorporationDirectory $directory)
    {
        $tracked = array_values(array_filter(array_map(
            fn ($id) => $directory->details($id),
            $settings->configuredCorporations()
        )));

        return view('ratting-manager::settings.index', [
            'trackedCorporations' => $tracked,
            'corporationList' => $directory->all(),
            'values' => [
                'session_gap_minutes' => $settings->get('data.session_gap_minutes'),
                'gone_quiet_days' => $settings->get('data.gone_quiet_days'),
                'regular_ratter_days' => $settings->get('data.regular_ratter_days'),
                'leaderboard_size' => $settings->get('leaderboard.size'),
                'raw_tick_days' => $settings->get('data.raw_tick_days'),
                'members_only' => $settings->get('access.members_only'),
                'watch_type_ids' => implode(', ', $settings->get('spawns.watch_type_ids')),
            ],
            'rawKeptFrom' => RawTicks::keptFrom(),
            'lastProcessAt' => $settings->get('state.last_process_at'),
            'lastReport' => $settings->get('state.last_process_report'),
            'initializedAt' => $settings->get('state.initialized_at'),
        ] + $this->notifications($settings, array_column($tracked, 'name', 'id')));
    }

    /**
     * One corporation's details, as the card the picker shows before it is
     * tracked.
     */
    public function corporation(int $corporationId, CorporationDirectory $directory)
    {
        $corporation = $directory->details($corporationId);
        abort_if($corporation === null, 404);

        return view('ratting-manager::settings.partials.corporation-card', [
            'corporation' => $corporation,
            'preview' => true,
        ]);
    }

    public function track(Request $request, Settings $settings)
    {
        $data = $request->validate(['corporation_id' => ['required', 'integer', 'exists:corporation_infos,corporation_id']]);
        $id = (int) $data['corporation_id'];
        $name = DB::table('corporation_infos')->where('corporation_id', $id)->value('name');

        $settings->set('general.configured_corporations', array_values(array_unique(array_merge($settings->configuredCorporations(), [$id]))));

        return redirect()->to(route('ratting-manager.settings.index') . '#tracking')
            ->with('success', RawTicks::keptFrom()
                ? trans('ratting-manager::settings.now_tracking_from', ['name' => $name, 'date' => RawTicks::keptFrom()->toDateString()])
                : trans('ratting-manager::settings.now_tracking', ['name' => $name]));
    }

    public function untrack(int $corporationId, Settings $settings)
    {
        $name = DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name') ?? '#' . $corporationId;
        $settings->set('general.configured_corporations', array_values(array_diff($settings->configuredCorporations(), [$corporationId])));

        return redirect()->to(route('ratting-manager.settings.index') . '#tracking')
            ->with('success', trans('ratting-manager::settings.stopped_tracking', ['name' => $name]));
    }

    /** What the Webhooks, Notifications and Routing Map sections show. */
    private function notifications(Settings $settings, array $trackedNames): array
    {
        $roles = DiscordRoleResolver::roles();
        $lookup = array_column($roles, null, 'id');
        $homeSpace = app(HomeSpace::class);

        return [
            'webhooks' => Webhook::orderBy('description')->get(),
            'routing' => app(RoutingMap::class)->build($lookup),
            'boundTypes' => RoutingMap::boundTypes(),
            'webhookHealth' => app(WebhookDeliveryService::class)->health(7),
            'alertTypes' => NotificationTypes::keys(NotificationTypes::ALERTS),
            'reportTypes' => NotificationTypes::keys(NotificationTypes::REPORTS),
            'roles' => $roles,
            'roleLookup' => $lookup,
            'roleProviders' => array_map([DiscordRoleResolver::class, 'providerLabel'], DiscordRoleResolver::providers()),
            'webhookCorporations' => $trackedNames,
            'notificationValues' => [
                'spawn_min_rank' => $settings->get('notifications.spawn_min_rank'),
                'spawn_capitals' => $settings->get('notifications.spawn_capitals'),
                'spawn_max_age_hours' => $settings->get('notifications.spawn_max_age_hours'),
                'stalled_hours' => $settings->get('notifications.stalled_hours'),
                'outsider_min_kills' => $settings->get('notifications.outsider_min_kills'),
                'ship_lost_minutes' => $settings->get('notifications.ship_lost_minutes'),
                'rankings_size' => $settings->get('notifications.rankings_size'),
            ],
            'homeSpace' => [
                'set' => $homeSpace->isSet(),
                'systems_watched' => count($homeSpace->systems()),
                'regions' => $homeSpace->names('regions'),
                'constellations' => $homeSpace->names('constellations'),
                'systems' => $homeSpace->names('systems'),
            ],
        ];
    }

    public function update(Request $request, Settings $settings)
    {
        $data = $request->validate([
            'session_gap_minutes' => ['required', 'integer', 'min:20', 'max:240'],
            'gone_quiet_days' => ['required', 'integer', 'min:1', 'max:365'],
            'regular_ratter_days' => ['required', 'integer', 'min:7', 'max:' . RawTicks::KEEP_DAYS, 'gt:gone_quiet_days'],
            'leaderboard_size' => ['required', 'integer', 'min:5', 'max:500'],
            'raw_tick_days' => ['required', 'integer', 'min:0', 'max:' . RawTicks::KEEP_DAYS, function ($attribute, $value, $fail) {
                if ((int) $value !== 0 && (int) $value < RawTicks::MIN_DAYS) {
                    $fail(trans('ratting-manager::settings.raw_tick_days_too_low', ['min' => RawTicks::MIN_DAYS]));
                }
            }],
            'watch_type_ids' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings->set('data.session_gap_minutes', $data['session_gap_minutes']);
        $settings->set('data.gone_quiet_days', $data['gone_quiet_days']);
        $settings->set('data.regular_ratter_days', $data['regular_ratter_days']);
        $settings->set('leaderboard.size', $data['leaderboard_size']);
        $settings->set('data.raw_tick_days', $data['raw_tick_days']);
        $settings->set('access.members_only', $request->boolean('members_only'));

        preg_match_all('/\d+/', (string) ($data['watch_type_ids'] ?? ''), $ids);
        $settings->set('spawns.watch_type_ids', $ids[0]);

        return redirect()->to(route('ratting-manager.settings.index') . '#tracking')
            ->with('success', trans('ratting-manager::settings.saved'));
    }
}
