<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Day by day totals per character, corporation and system, which every
 * chart and long-range figure reads instead of the ticks themselves.
 *
 * A day is rebuilt whole: its rows are removed and written again from the
 * ticks and payouts, so a tick paired or worked out later shows up the next
 * time the day is built.
 */
class DailySummaryBuilder
{
    /** How many days rebuild() will go through, for a progress bar. */
    public static function dayCount(Carbon $from, ?Carbon $to = null): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays(($to ?? now())->copy()->startOfDay()) + 1;
    }

    public function rebuild(Carbon $from, ?Carbon $to = null, ?Progress $progress = null): array
    {
        $progress ??= Progress::none();
        $start = RawTicks::clamp($from)->copy()->startOfDay();
        $end = ($to ?? now())->copy()->endOfDay();

        $result = ['summaries' => 0, 'kills' => 0];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            [$summaries, $kills] = $this->rebuildDay($day->copy());
            $result['summaries'] += $summaries;
            $result['kills'] += $kills;
            $progress->advance();
        }

        return $result;
    }

    /**
     * Rebuild only the days given, for a run that knows which days its
     * import touched.
     *
     * @param string[] $dates Y-m-d
     */
    public function rebuildDays(array $dates, ?Progress $progress = null): array
    {
        $progress ??= Progress::none();
        $result = ['summaries' => 0, 'kills' => 0];

        $dates = array_values(array_unique(RawTicks::heldDates($dates)));
        sort($dates);

        foreach ($dates as $date) {
            [$summaries, $kills] = $this->rebuildDay(Carbon::parse($date));
            $result['summaries'] += $summaries;
            $result['kills'] += $kills;
            $progress->advance();
        }

        return $result;
    }

    /**
     * One corporation's rows on days filled behind the line, every other
     * corporation's rows on those days left as they are. No daily kill totals
     * are written: behind the line they have gone with the ticks, and the
     * character days carry the kill lists.
     *
     * @param string[] $dates Y-m-d
     */
    public function rebuildCorporationDays(array $dates, int $corporationId, ?Progress $progress = null): int
    {
        $progress ??= Progress::none();
        $written = 0;

        foreach ($dates as $date) {
            [$summaries] = $this->rebuildDay(Carbon::parse($date), $corporationId);
            $written += $summaries;
            $progress->advance();
        }

        return $written;
    }

    private function rebuildDay(Carbon $day, ?int $corporationId = null): array
    {
        $date = $day->toDateString();
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();
        $now = now();
        $ours = fn ($query) => $corporationId === null ? $query : $query->where('corporation_id', $corporationId);

        $rows = [];
        $key = fn ($c, $corp, $s) => $c . ':' . $corp . ':' . (int) $s;
        $blank = fn ($c, $corp, $s) => [
            'date' => $date, 'character_id' => $c, 'corporation_id' => $corp, 'solar_system_id' => (int) $s,
            'ticks' => 0, 'minutes' => 0,
            'bounty_net' => 0.0, 'bounty_estimated' => 0.0, 'bounty_corp_tax' => 0.0,
            'ess_net' => 0.0, 'ess_estimated' => 0.0, 'ess_corp_tax' => 0.0,
            'npc_kills' => 0, 'created_at' => $now, 'updated_at' => $now,
        ];

        $ticks = $ours(DB::table('ratting_manager_ticks')->whereBetween('ticked_at', [$dayStart, $dayEnd]))
            ->get(['character_id', 'corporation_id', 'solar_system_id', 'ticked_at', 'net_amount', 'net_is_estimated', 'corp_tax_amount', 'npc_kills']);

        $times = [];
        foreach ($ticks as $t) {
            $k = $key($t->character_id, $t->corporation_id, $t->solar_system_id);
            $rows[$k] ??= $blank((int) $t->character_id, (int) $t->corporation_id, $t->solar_system_id);
            $rows[$k]['ticks']++;
            $rows[$k]['bounty_net'] += (float) $t->net_amount;
            $rows[$k]['bounty_estimated'] += $t->net_is_estimated ? (float) $t->net_amount : 0.0;
            $rows[$k]['bounty_corp_tax'] += (float) $t->corp_tax_amount;
            $rows[$k]['npc_kills'] += (int) $t->npc_kills;
            $times[$k][] = strtotime($t->ticked_at);
        }

        foreach ($times as $k => $list) {
            $rows[$k]['minutes'] = ActiveTime::minutes($list);
        }

        $payouts = $ours(DB::table('ratting_manager_ess_payouts')->whereBetween('paid_at', [$dayStart, $dayEnd]))
            ->get(['character_id', 'corporation_id', 'solar_system_id', 'net_amount', 'net_is_estimated', 'corp_tax_amount']);

        foreach ($payouts as $p) {
            $k = $key($p->character_id, $p->corporation_id, $p->solar_system_id);
            $rows[$k] ??= $blank((int) $p->character_id, (int) $p->corporation_id, $p->solar_system_id);
            $rows[$k]['ess_net'] += (float) $p->net_amount;
            $rows[$k]['ess_estimated'] += $p->net_is_estimated ? (float) $p->net_amount : 0.0;
            $rows[$k]['ess_corp_tax'] += (float) $p->corp_tax_amount;
        }

        if ($corporationId !== null) {
            DB::transaction(function () use ($date, $rows, $corporationId) {
                DB::table('ratting_manager_daily_summaries')->where('date', $date)->where('corporation_id', $corporationId)->delete();
                $this->insertSummaries($rows);
            });

            return [count($rows), 0];
        }

        // Rats killed per corporation and system, once per kill group, so a
        // fleet that shared a payout is not counted once per member.
        $kills = DB::query()
            ->fromSub(
                DB::table('ratting_manager_ticks as t')
                    ->join('ratting_manager_tick_kills as k', 'k.tick_id', '=', 't.id')
                    ->whereBetween('t.ticked_at', [$dayStart, $dayEnd])
                    ->whereNotNull('t.kill_group')
                    ->select(['t.corporation_id', 't.solar_system_id', 't.kill_group', 'k.npc_type_id', 'k.quantity'])
                    ->distinct(),
                'x'
            )
            ->groupBy('corporation_id', 'solar_system_id', 'npc_type_id')
            ->get(['corporation_id', 'solar_system_id', 'npc_type_id', DB::raw('SUM(quantity) as quantity')]);

        $killers = DB::table('ratting_manager_ticks as t')
            ->join('ratting_manager_tick_kills as k', 'k.tick_id', '=', 't.id')
            ->whereBetween('t.ticked_at', [$dayStart, $dayEnd])
            ->groupBy('t.corporation_id', 't.solar_system_id', 'k.npc_type_id')
            ->get(['t.corporation_id', 't.solar_system_id', 'k.npc_type_id', DB::raw('COUNT(DISTINCT t.character_id) as characters')])
            ->keyBy(fn ($r) => $r->corporation_id . ':' . $r->solar_system_id . ':' . $r->npc_type_id);

        $killRows = [];
        foreach ($kills as $r) {
            $killRows[] = [
                'date' => $date,
                'corporation_id' => (int) $r->corporation_id,
                'solar_system_id' => (int) $r->solar_system_id,
                'npc_type_id' => (int) $r->npc_type_id,
                'quantity' => (int) $r->quantity,
                'characters' => (int) ($killers[$r->corporation_id . ':' . $r->solar_system_id . ':' . $r->npc_type_id]->characters ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($date, $rows, $killRows) {
            DB::table('ratting_manager_daily_summaries')->where('date', $date)->delete();
            DB::table('ratting_manager_daily_kills')->where('date', $date)->delete();

            $this->insertSummaries($rows);

            foreach (array_chunk($killRows, 500) as $chunk) {
                DB::table('ratting_manager_daily_kills')->insert($chunk);
            }
        });

        return [count($rows), count($killRows)];
    }

    private function insertSummaries(array $rows): void
    {
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            foreach ($chunk as &$row) {
                foreach (['bounty_net', 'bounty_estimated', 'bounty_corp_tax', 'ess_net', 'ess_estimated', 'ess_corp_tax'] as $f) {
                    $row[$f] = round($row[$f], 2);
                }
            }
            unset($row);
            DB::table('ratting_manager_daily_summaries')->insert($chunk);
        }
    }
}
