<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Npc\NpcRegistry;
use RattingManager\Services\Notification\Message;

/**
 * A special spawn killed by a tracked ratter. One post per spawn: the NPCs
 * of one fleet payout go out together, whoever shared it.
 */
class SpawnAlert extends Alert
{
    public function type(): string
    {
        return 'special_spawn';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type()) || $this->corporations() === []) {
            return 0;
        }

        $rows = DB::table('ratting_manager_special_spawns as s')
            ->leftJoin('ratting_manager_npc_types as n', 'n.type_id', '=', 's.npc_type_id')
            ->whereNull('s.notified_at')
            ->whereIn('s.corporation_id', $this->corporations())
            ->where('s.ticked_at', '>=', Carbon::now()->subHours(max(1, (int) $this->settings->get('notifications.spawn_max_age_hours'))))
            ->orderBy('s.ticked_at')
            ->get(['s.id', 's.kill_group', 's.npc_type_id', 's.quantity', 's.character_id', 's.corporation_id', 's.solar_system_id', 's.ticked_at', 'n.name', 'n.faction', 'n.size', 'n.rank']);

        $sent = 0;
        foreach ($rows->groupBy('kill_group') as $group => $spawns) {
            $wanted = $spawns->filter(fn ($s) => $this->wanted($s))->values();
            if ($wanted->isEmpty()) {
                continue;
            }

            $first = $wanted->first();
            $characters = DB::table('ratting_manager_ticks')->where('kill_group', $group)->distinct()->pluck('character_id')->map(fn ($id) => (int) $id)->all();
            $this->players->load($characters);

            $result = $this->notifier->send($this->type(), (int) $first->corporation_id, $this->message(
                $wanted->map(fn ($s) => [
                    'type_id' => (int) $s->npc_type_id,
                    'name' => $s->name ?? 'Type ' . $s->npc_type_id,
                    'quantity' => (int) $s->quantity,
                    'faction' => $s->faction ?? 'other',
                    'rank' => $s->rank ?? 'regular',
                    'size' => $s->size ?? 'unknown',
                ])->all(),
                array_map(fn ($id) => $this->players->name($id), $characters ?: [(int) $first->character_id]),
                (int) $first->solar_system_id,
                $first->ticked_at
            ));

            if ($this->done($result)) {
                DB::table('ratting_manager_special_spawns')->where('kill_group', $group)->update(['notified_at' => Carbon::now()]);
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->message(
            [['type_id' => 13537, 'name' => 'Estamel Tharchon', 'quantity' => 1, 'faction' => 'guristas', 'rank' => 'officer', 'size' => 'large']],
            ['Rykel Fairchilde', 'Sineryn Trantus'],
            30003841,
            Carbon::now()->subMinutes(40)
        );
    }

    /** Ranks from the minimum up, capitals if switched on, and anything on the watch list. */
    private function wanted($spawn): bool
    {
        if (in_array((int) $spawn->npc_type_id, $this->settings->get('spawns.watch_type_ids'), true)) {
            return true;
        }
        if ($spawn->size === 'capital' && $this->settings->get('notifications.spawn_capitals')) {
            return true;
        }

        $ranks = NpcRegistry::RANKS;
        $min = array_search($this->settings->get('notifications.spawn_min_rank'), $ranks, true);
        $rank = array_search($spawn->rank ?? 'regular', $ranks, true);

        // A rank that is no longer one, kept from before the ranks changed,
        // reads as the lowest special rank.
        if ($min === false || $min === 0) {
            $min = 1;
        }

        return $rank !== false && $rank >= $min;
    }

    private function message(array $npcs, array $characters, int $systemId, $when): Message
    {
        $first = $npcs[0];
        $title = count($npcs) > 1
            ? trans('ratting-manager::notifications.spawn_title_more', ['npc' => $first['name'], 'count' => count($npcs) - 1])
            : trans('ratting-manager::notifications.spawn_title', ['npc' => $first['name']]);

        $lines = array_map(fn ($n) => ($n['quantity'] > 1 ? $n['quantity'] . 'x ' : '') . '**' . $n['name'] . '**, '
            . (NpcRegistry::FACTIONS[$n['faction']] ?? ucfirst($n['faction'])) . ' '
            . strtolower(trans('ratting-manager::common.rank_' . $n['rank'])) . ', '
            . strtolower(trans('ratting-manager::common.size_' . $n['size'])), $npcs);

        return Message::forType($this->type(), $title)
            ->description(trans(count($characters) > 1 ? 'ratting-manager::notifications.spawn_by_fleet' : 'ratting-manager::notifications.spawn_by', [
                'who' => self::nameList($characters),
                'system' => $this->systemLabel($systemId),
            ]))
            ->field(trans('ratting-manager::notifications.npc'), implode("\n", $lines), false)
            ->field(trans('ratting-manager::notifications.system'), $this->systemLabel($systemId))
            ->field(trans('ratting-manager::notifications.when'), self::eveTime($when) . ' ' . trans('ratting-manager::notifications.eve'))
            ->url($this->dotlan($systemId))
            ->thumbnail('https://images.evetech.net/types/' . $first['type_id'] . '/icon?size=64')
            ->at(Carbon::parse($when));
    }
}
