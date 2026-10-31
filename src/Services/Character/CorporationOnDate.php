<?php

namespace RattingManager\Services\Character;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The corporation a character was in at a given moment, for journal rows that
 * do not say. A taxed bounty names who took the tax; an untaxed one or an ESS
 * payout names nobody, so the employment history decides. A member who later
 * leaves keeps their ticks in the corporation they ratted for.
 *
 * SeAT's copy of the history can be a day behind the journal, so a move on file
 * that it does not show yet is taken into account. Without that, a tick after
 * leaving would be credited to the corporation they left.
 */
class CorporationOnDate
{
    /** @var array<int, array<int, array{0: int, 1: int}>> character_id => [[start timestamp, corporation_id], ...] oldest first */
    private array $history = [];

    /** @var array<int, int> character_id => current corporation_id */
    private array $current = [];

    /** @var array<int, array<int, array{0: int, 1: int, 2: ?int}>> character_id => [[corporation_id, left at timestamp, next corporation_id], ...] */
    private array $departures = [];

    /** @param int[] $characterIds */
    public function load(array $characterIds): void
    {
        $missing = array_values(array_diff($characterIds, array_keys($this->history)));
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunk) {
            foreach ($chunk as $id) {
                $this->history[$id] = [];
            }

            $rows = DB::table('character_corporation_histories')
                ->whereIn('character_id', $chunk)
                ->orderBy('start_date')
                ->get(['character_id', 'corporation_id', 'start_date']);

            foreach ($rows as $row) {
                $this->history[(int) $row->character_id][] = [
                    Carbon::parse($row->start_date)->getTimestamp(),
                    (int) $row->corporation_id,
                ];
            }

            $this->current += DB::table('character_affiliations')
                ->whereIn('character_id', $chunk)
                ->pluck('corporation_id', 'character_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach (DB::table(Departures::TABLE)->whereIn('character_id', $chunk)->get(['character_id', 'corporation_id', 'left_at', 'next_corporation_id']) as $row) {
                $this->departures[(int) $row->character_id][] = [
                    (int) $row->corporation_id,
                    Carbon::parse($row->left_at)->getTimestamp(),
                    $row->next_corporation_id === null ? null : (int) $row->next_corporation_id,
                ];
            }
        }
    }

    public function at(int $characterId, $moment): ?int
    {
        $this->load([$characterId]);

        $time = Carbon::parse($moment)->getTimestamp();
        $found = null;
        $since = null;

        foreach ($this->history[$characterId] as [$start, $corporationId]) {
            if ($start > $time) {
                break;
            }
            $found = $corporationId;
            $since = $start;
        }

        if ($found === null) {
            // No history row reaches back that far, or SeAT has none at all: the
            // current corporation is the best that is known.
            return $this->current[$characterId] ?? null;
        }

        foreach ($this->departures[$characterId] ?? [] as [$corporationId, $leftAt, $next]) {
            if ($corporationId === $found && $leftAt > $since && $leftAt <= $time) {
                return $next;
            }
        }

        return $found;
    }
}
