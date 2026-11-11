<?php

namespace RattingManager\Services\Import;

use Illuminate\Support\Facades\DB;
use RattingManager\Models\Tick;

class KillRecorder
{
    /**
     * Keep the rats a tick paid for. Both journals carry the same kill list,
     * so whichever side arrives first records it and the other leaves it.
     */
    public function record(Tick $tick, ?string $reason): void
    {
        $kills = $this->prepare($tick, $reason);
        if ($kills === []) {
            return;
        }

        $tick->save();
        DB::table('ratting_manager_tick_kills')->insertOrIgnore($this->rows((int) $tick->id, $kills));
    }

    /**
     * Put the kill group and kill count on a tick without saving it, so the
     * caller can write the tick once with everything on it.
     *
     * @return array<int, int> npc_type_id => quantity to store once the tick
     *                         has an id; empty when there is nothing to store
     *                         (no kill list, or the other journal's row
     *                         already recorded it)
     */
    public function prepare(Tick $tick, ?string $reason): array
    {
        if ($tick->kill_group !== null) {
            return [];
        }

        $kills = ReasonParser::kills($reason);
        if ($kills === []) {
            return [];
        }

        $tick->kill_group = ReasonParser::killGroup(
            $tick->ticked_at->format('Y-m-d H:i:s'),
            $tick->solar_system_id,
            $kills
        );
        $tick->npc_kills = array_sum($kills);

        return $kills;
    }

    /**
     * The tick_kills rows for a tick's kills.
     *
     * @param array<int, int> $kills npc_type_id => quantity, from prepare()
     */
    public function rows(int $tickId, array $kills): array
    {
        $rows = [];
        foreach ($kills as $typeId => $quantity) {
            $rows[] = ['tick_id' => $tickId, 'npc_type_id' => $typeId, 'quantity' => $quantity];
        }

        return $rows;
    }
}
