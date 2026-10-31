<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;

/**
 * Characters whose wallet token has been lost or revoked, and how long there
 * is to fix it.
 *
 * While a token is gone, the corporation journal still shows every tick the
 * corporation took a cut of, with the income worked out from that cut, so
 * nothing goes missing from the figures. What goes is the exact amount, and
 * any tick the corporation took no cut of. When the token comes back, SeAT
 * walks the journal back to where it stopped, and the next import pairs what it
 * finds. ESI only serves 30 days of journal, though, so from 30 days after the
 * token went the oldest days start to be out of reach for good. This is what
 * lets someone act before that.
 */
class WalletGaps
{
    /** How far back ESI serves a wallet journal. */
    public const ESI_DAYS = 30;

    /** SeAT pulls journals about hourly, so a character's rows can run behind the corporation's. */
    private const LAG_HOURS = 6;

    /** Past this nothing can come back, and a tick this old says nothing about a token now. */
    private const LOOK_BACK_DAYS = 45;

    private Players $players;

    public function __construct(Players $players)
    {
        $this->players = $players;
    }

    /**
     * @param int[] $corporations
     * @return array<int, array{character_id: int, name: string, corporation_id: int, state: string, since: string, ticks: int, worked_out: float, fix_by: string, days_left: int}>
     *         soonest to run out first
     */
    public function forCorporations(array $corporations): array
    {
        if ($corporations === []) {
            return [];
        }

        $characters = DB::table('character_affiliations as a')
            ->join('refresh_tokens as t', 't.character_id', '=', 'a.character_id')
            ->whereIn('a.corporation_id', $corporations)
            ->get(['a.character_id', 'a.corporation_id', 't.deleted_at', 't.scopes']);

        $gaps = $this->gaps($characters);
        usort($gaps, fn ($a, $b) => [$a['fix_by'], $a['character_id']] <=> [$b['fix_by'], $b['character_id']]);

        return $gaps;
    }

    /**
     * @param int[] $characterIds
     * @return array<int, array> character_id => the gap, for those who have one
     */
    public function forCharacters(array $characterIds): array
    {
        $characters = DB::table('character_affiliations as a')
            ->join('refresh_tokens as t', 't.character_id', '=', 'a.character_id')
            ->whereIn('a.character_id', $characterIds ?: [0])
            ->get(['a.character_id', 'a.corporation_id', 't.deleted_at', 't.scopes']);

        $gaps = [];
        foreach ($this->gaps($characters) as $gap) {
            $gaps[$gap['character_id']] = $gap;
        }

        return $gaps;
    }

    private function gaps($characters): array
    {
        if ($characters->isEmpty()) {
            return [];
        }

        $ids = $characters->pluck('character_id')->map(fn ($id) => (int) $id)->all();
        $since = now()->subDays(self::LOOK_BACK_DAYS);

        $lastOwn = DB::table('character_wallet_journals')
            ->whereIn('character_id', $ids)
            ->groupBy('character_id')
            ->selectRaw('character_id, MAX(date) as last_row')
            ->pluck('last_row', 'character_id');

        $corporationOnly = DB::table('ratting_manager_ticks')
            ->whereIn('character_id', $ids)
            ->where('kind', 'corporation')
            ->where('ticked_at', '>=', $since)
            ->orderBy('ticked_at')
            ->get(['character_id', 'ticked_at', 'net_amount'])
            ->groupBy('character_id');

        $this->players->load($ids);

        $gaps = [];
        foreach ($characters as $row) {
            $id = (int) $row->character_id;
            $scopes = json_decode((string) $row->scopes, true) ?: [];
            $state = $row->deleted_at !== null ? 'removed' : (in_array(Players::WALLET_SCOPE, $scopes, true) ? 'live' : 'no_scope');

            $last = $lastOwn[$id] ?? null;
            $after = $last === null ? null : Carbon::parse($last)->addHours(self::LAG_HOURS);
            $ticks = collect($corporationOnly->get($id, []))
                ->filter(fn ($tick) => $after === null || Carbon::parse($tick->ticked_at)->gt($after));

            // A token that went is news while its days can still come back,
            // or while its character is still ratting. One that looks fine is
            // only lost if the corporation is seeing ticks its journal is not.
            $start = $ticks->isEmpty() ? null : Carbon::parse($ticks->first()->ticked_at);
            if ($state === 'removed') {
                $removed = Carbon::parse($row->deleted_at);
                $start = $start === null || $removed->lt($start) ? $removed : $start;
                if ($ticks->isEmpty() && $removed->lt(now()->subDays(self::ESI_DAYS))) {
                    continue;
                }
            } elseif ($start === null) {
                continue;
            }

            $fixBy = $start->copy()->addDays(self::ESI_DAYS);

            $gaps[] = [
                'character_id' => $id,
                'name' => $this->players->name($id),
                'corporation_id' => (int) $row->corporation_id,
                'state' => $state,
                'since' => $start->format('Y-m-d H:i:s'),
                'ticks' => $ticks->count(),
                'worked_out' => round($ticks->sum(fn ($tick) => (float) $tick->net_amount), 2),
                'fix_by' => $fixBy->format('Y-m-d H:i:s'),
                'days_left' => max(0, (int) ceil(now()->diffInSeconds($fixBy, false) / 86400)),
            ];
        }

        return $gaps;
    }
}
