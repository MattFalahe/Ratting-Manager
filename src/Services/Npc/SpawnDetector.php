<?php

namespace RattingManager\Services\Npc;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;

class SpawnDetector
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * List the notable rats killed since the given time. A spawn shared by a
     * fleet is one spawn: the first tick of its kill group stands for it.
     */
    public function detect(Carbon $since): int
    {
        $watched = $this->settings->get('spawns.watch_type_ids');

        $query = DB::table('ratting_manager_ticks as t')
            ->join('ratting_manager_tick_kills as k', 'k.tick_id', '=', 't.id')
            ->join('ratting_manager_npc_types as n', 'n.type_id', '=', 'k.npc_type_id')
            ->where('t.ticked_at', '>=', $since)
            ->whereNotNull('t.kill_group')
            ->where(function ($q) use ($watched) {
                $q->whereIn('n.rank', NpcRegistry::SPECIAL_RANKS)
                    ->orWhereIn('n.size', NpcRegistry::SPECIAL_SIZES);
                if ($watched !== []) {
                    $q->orWhereIn('n.type_id', $watched);
                }
            })
            ->get(['t.id', 't.kill_group', 't.character_id', 't.corporation_id', 't.solar_system_id', 't.ticked_at', 'k.npc_type_id', 'k.quantity']);

        $now = now();
        $rows = [];
        // Sorted here: asked of the database, it walks the whole tick table in
        // id order to avoid sorting these few rows.
        foreach ($query->sortBy('id') as $row) {
            $rows[$row->kill_group . ':' . $row->npc_type_id] ??= [
                'kill_group' => $row->kill_group,
                'npc_type_id' => (int) $row->npc_type_id,
                'quantity' => (int) $row->quantity,
                'tick_id' => (int) $row->id,
                'character_id' => (int) $row->character_id,
                'corporation_id' => (int) $row->corporation_id,
                'solar_system_id' => (int) $row->solar_system_id,
                'ticked_at' => $row->ticked_at,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = 0;
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            $inserted += DB::table('ratting_manager_special_spawns')->insertOrIgnore($chunk);
        }

        return $inserted;
    }

    /**
     * After the NPC types were classified again: drop the spawns that no
     * longer count and find the ones that now do, over every tick on file.
     *
     * @return array{removed: int, found: int}
     */
    public function rebuild(): array
    {
        $watched = $this->settings->get('spawns.watch_type_ids');

        $stale = DB::table('ratting_manager_special_spawns as s')
            ->join('ratting_manager_npc_types as n', 'n.type_id', '=', 's.npc_type_id')
            ->whereNotIn('n.rank', NpcRegistry::SPECIAL_RANKS)
            ->whereNotIn('n.size', NpcRegistry::SPECIAL_SIZES)
            ->whereNotIn('s.npc_type_id', $watched ?: [0])
            ->pluck('s.id')
            ->all();

        foreach (array_chunk($stale, 1000) as $chunk) {
            DB::table('ratting_manager_special_spawns')->whereIn('id', $chunk)->delete();
        }

        $first = DB::table('ratting_manager_ticks')->min('ticked_at');

        return [
            'removed' => count($stale),
            'found' => $first ? $this->detect(Carbon::parse($first)) : 0,
        ];
    }
}
