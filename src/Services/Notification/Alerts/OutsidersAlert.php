<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Universe\HomeSpace;

/**
 * More NPCs died in a home system in an hour than the tracked corporations'
 * ratters killed there: someone else is ratting in their space.
 *
 * Home is the space listed under Notifications. With none listed nothing is
 * watched and nothing is sent, whatever the switch says.
 *
 * ESI's counts are kept hourly by ratting-manager:poll-system-kills. An hour
 * is looked at once it is three hours old, when the journals for it are in,
 * and for a day after that.
 */
class OutsidersAlert extends Alert
{
    private const SETTLE_HOURS = 3;
    private const LOOK_BACK_HOURS = 24;

    /** The corporation that rats in a system most over this many days hears about outsiders there. */
    private const RATTED_DAYS = 14;

    public function type(): string
    {
        return 'outsiders';
    }

    /** @return int[] */
    public function watchedSystems(): array
    {
        return app(HomeSpace::class)->systems();
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type()) || $this->corporations() === []) {
            return 0;
        }

        $systems = $this->watchedSystems();
        if ($systems === []) {
            return 0;
        }

        $min = max(1, (int) $this->settings->get('notifications.outsider_min_kills'));
        $hours = DB::table('ratting_manager_system_kills')
            ->whereIn('solar_system_id', $systems)
            ->whereBetween('hour', [Carbon::now()->subHours(self::LOOK_BACK_HOURS), Carbon::now()->subHours(self::SETTLE_HOURS)])
            ->where('npc_kills', '>=', $min)
            ->orderBy('hour')
            ->get();

        $sent = 0;
        foreach ($hours as $row) {
            $hour = Carbon::parse($row->hour);
            $key = 'outsiders:' . $row->solar_system_id . ':' . $hour->format('YmdH');
            if ($this->state->isOpen($key)) {
                continue;
            }

            [$ours, $players, $corporation] = $this->ours((int) $row->solar_system_id, $hour);
            $others = (int) $row->npc_kills - $ours;
            if ($others < $min) {
                continue;
            }

            $result = $this->notifier->send($this->type(), $corporation, $this->message(
                (int) $row->solar_system_id, $hour, (int) $row->npc_kills, $ours, (int) $row->ship_kills, (int) $row->pod_kills, $players
            ));

            if ($this->done($result)) {
                $this->state->opened($key, ['npc_kills' => (int) $row->npc_kills, 'ours' => $ours]);
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->message(30003841, Carbon::now()->subHours(4)->startOfHour(), 312, 40, 2, 1, 1);
    }

    /**
     * What the tracked corporations killed there in the hour, a fleet's
     * payout counted once, how many players that was, and the corporation
     * that rats there most, whose webhooks hear about it.
     *
     * @return array{0: int, 1: int, 2: ?int}
     */
    private function ours(int $systemId, Carbon $hour): array
    {
        $ticks = DB::table('ratting_manager_ticks')
            ->whereIn('corporation_id', $this->corporations())
            ->where('solar_system_id', $systemId)
            ->where('ticked_at', '>', $hour)
            ->where('ticked_at', '<=', $hour->copy()->addHour())
            ->get(['character_id', 'kill_group', 'npc_kills']);

        $groups = [];
        $mains = [];
        foreach ($ticks as $t) {
            if ($t->kill_group !== null) {
                $groups[$t->kill_group] = (int) $t->npc_kills;
            }
            $mains[$this->players->mainOf((int) $t->character_id)] = true;
        }

        $corporation = DB::table('ratting_manager_ticks')
            ->whereIn('corporation_id', $this->corporations())
            ->where('solar_system_id', $systemId)
            ->where('ticked_at', '>=', Carbon::now()->subDays(self::RATTED_DAYS))
            ->groupBy('corporation_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('corporation_id');

        return [array_sum($groups), count($mains), $corporation ? (int) $corporation : null];
    }

    private function message(int $systemId, Carbon $hour, int $total, int $ours, int $ships, int $pods, int $players): Message
    {
        $system = $this->systemLabel($systemId);

        return Message::forType($this->type(), trans('ratting-manager::notifications.outsiders_title', ['system' => $this->systems->name($systemId)]))
            ->description(trans('ratting-manager::notifications.outsiders_body', [
                'from' => $hour->format('H:i'),
                'to' => $hour->copy()->addHour()->format('H:i'),
                'date' => $hour->format('j M'),
                'total' => number_format($total),
                'system' => $system,
                'ours' => number_format($ours),
                'others' => number_format($total - $ours),
            ]))
            ->field(trans('ratting-manager::notifications.our_ratters'), trans('ratting-manager::notifications.players_count', ['count' => $players]))
            ->field(trans('ratting-manager::notifications.ship_kills'), (string) $ships)
            ->field(trans('ratting-manager::notifications.pod_kills'), (string) $pods)
            ->url($this->dotlan($systemId))
            ->at($hour->copy()->addHour());
    }
}
