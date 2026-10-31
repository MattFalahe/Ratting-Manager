<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes each character's day of ticks into ratting_manager_character_days,
 * which CharacterDays reads back.
 *
 * A day is rebuilt whole, as the daily summaries are, so a tick paired or
 * worked out later shows up the next time its day is built.
 */
class CharacterDayBuilder
{
    /** @return string[] every day with a tick, oldest first */
    public static function allDates(): array
    {
        return DB::table('ratting_manager_ticks')
            ->selectRaw('DATE(ticked_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => substr((string) $day, 0, 10))
            ->all();
    }

    /** @return string[] every day from this one to today */
    public static function datesFrom(Carbon $from): array
    {
        $dates = [];
        $end = now()->startOfDay();
        for ($day = $from->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * @param string[] $dates Y-m-d
     * @return int rows written
     */
    public function rebuildDays(array $dates, ?Progress $progress = null): int
    {
        $progress ??= Progress::none();
        $written = 0;

        foreach (RawTicks::heldDates($dates) as $date) {
            $written += $this->rebuildDay($date);
            $progress->advance();
        }

        return $written;
    }

    private function rebuildDay(string $date): int
    {
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $now = now();

        $days = [];
        $ticks = DB::table('ratting_manager_ticks')
            ->whereBetween('ticked_at', [$dayStart, $dayEnd])
            ->orderBy('ticked_at')
            ->orderBy('id')
            ->select(['character_id', 'corporation_id', 'solar_system_id', 'net_amount', 'npc_kills', 'kill_group', 'kind'])
            ->selectRaw('TIMESTAMPDIFF(SECOND, ?, ticked_at) as second_of_day', [$dayStart])
            ->cursor();

        foreach ($ticks as $t) {
            $key = $t->character_id . ':' . $t->corporation_id;
            $days[$key] ??= [
                'date' => $date,
                'character_id' => (int) $t->character_id,
                'corporation_id' => (int) $t->corporation_id,
                'tick_count' => 0,
                'ticks' => [],
                'kills' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $days[$key]['tick_count']++;
            $days[$key]['ticks'][] = [
                (int) $t->second_of_day,
                (int) $t->solar_system_id,
                (string) $t->net_amount,
                (int) $t->npc_kills,
                $t->kill_group === null ? null : substr($t->kill_group, 0, CharacterDays::KILL_GROUP_LENGTH),
                CharacterDays::KINDS[$t->kind] ?? $t->kind,
            ];
        }

        $kills = DB::table('ratting_manager_ticks as t')
            ->join('ratting_manager_tick_kills as k', 'k.tick_id', '=', 't.id')
            ->whereBetween('t.ticked_at', [$dayStart, $dayEnd])
            ->whereNotNull('t.kill_group')
            ->select(['t.character_id', 't.corporation_id', 't.kill_group', 'k.npc_type_id', 'k.quantity'])
            ->cursor();

        foreach ($kills as $k) {
            $key = $k->character_id . ':' . $k->corporation_id;
            if (isset($days[$key])) {
                $group = substr($k->kill_group, 0, CharacterDays::KILL_GROUP_LENGTH);
                $days[$key]['kills'][$group][(int) $k->npc_type_id] = (int) $k->quantity;
            }
        }

        $rows = array_map(fn ($day) => [
            'ticks' => json_encode($day['ticks']),
            // An object even when empty, the shape every other row has.
            'kills' => json_encode((object) $day['kills']),
        ] + $day, array_values($days));

        DB::transaction(function () use ($date, $rows) {
            DB::table(CharacterDays::TABLE)->where('date', $date)->delete();

            // A busy character's row runs to a few kilobytes, so the chunks
            // stay small enough for any max_allowed_packet.
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table(CharacterDays::TABLE)->insert($chunk);
            }
        });

        return count($rows);
    }
}
