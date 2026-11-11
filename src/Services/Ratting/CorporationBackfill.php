<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Import\JournalImporter;
use RattingManager\Services\Npc\NpcClassifier;
use RattingManager\Services\Npc\SpawnDetector;
use Throwable;

/**
 * Fills the days behind the line for a corporation tracked late, or tracked
 * again after a break, from SeAT's journals.
 *
 * Behind the line every other corporation's days are summaries only, and a
 * day rebuilt the usual way would be built from ticks that are gone. So this
 * imports one corporation's rows alone, writes its rows on the days it has
 * none for, touches nothing else, and deletes its raw rows behind the line
 * again, as everyone else's have been. A day the corporation already has
 * rows for is left as it is, so nothing it shows can shrink.
 *
 * It all runs in one transaction: a run stopped part way leaves nothing behind.
 */
class CorporationBackfill
{
    private JournalImporter $importer;
    private TaxRates $taxRates;
    private NpcClassifier $npcs;
    private SpawnDetector $spawns;
    private SessionBuilder $sessions;
    private DailySummaryBuilder $summaries;
    private CharacterDayBuilder $characterDays;
    private MonthlySummaryBuilder $monthly;

    public function __construct(
        JournalImporter $importer,
        TaxRates $taxRates,
        NpcClassifier $npcs,
        SpawnDetector $spawns,
        SessionBuilder $sessions,
        DailySummaryBuilder $summaries,
        CharacterDayBuilder $characterDays,
        MonthlySummaryBuilder $monthly
    ) {
        $this->importer = $importer;
        $this->taxRates = $taxRates;
        $this->npcs = $npcs;
        $this->spawns = $spawns;
        $this->sessions = $sessions;
        $this->summaries = $summaries;
        $this->characterDays = $characterDays;
        $this->monthly = $monthly;
    }

    /**
     * @return array<string, mixed> state is no_line, nothing_behind, filled, nothing_to_fill or waiting_on_rate
     */
    public function fill(int $corporationId, Carbon $from): array
    {
        $line = RawTicks::keptFrom();
        if ($line === null) {
            return ['state' => 'no_line'];
        }

        $from = $from->copy()->startOfDay()->max(RawTicks::oldestDay());
        if ($from->gte($line)) {
            return ['state' => 'nothing_behind', 'line' => $line->toDateString()];
        }

        $report = ['from' => $from->toDateString(), 'line' => $line->toDateString()];

        DB::beginTransaction();

        try {
            $had = DB::table(Summaries::DAILY)
                ->where('corporation_id', $corporationId)
                ->whereBetween('date', [$from->toDateString(), $line->copy()->subDay()->toDateString()])
                ->distinct()
                ->pluck('date')
                ->map(fn ($date) => substr((string) $date, 0, 10))
                ->all();

            $import = $this->importer->importBehindLine([$corporationId], $from, $line);
            $report['rows_failed'] = 0;
            foreach ($import as $journal) {
                $report['rows_failed'] += $journal['bounty']['failed'] + $journal['ess']['failed'];
            }

            // Days the corporation has rows for keep them, even where SeAT
            // holds more of their journal than the summaries do.
            $days = array_values(array_diff($this->daysWithRows($corporationId, $from, $line), $had));
            sort($days);
            $report['days'] = count($days);
            $report['days_kept'] = count($had);

            if ($days !== []) {
                $this->taxRates->learn([$corporationId], $from, null, $line);
                $estimates = $this->taxRates->estimate([$corporationId], $from, null, $line);

                // A tick whose rate is not known yet would be built with no
                // income and then deleted, its income lost for good.
                if ($estimates['no_rate'] > 0) {
                    DB::rollBack();

                    return $report + ['state' => 'waiting_on_rate', 'no_rate' => $estimates['no_rate']];
                }

                $this->npcs->sync();
                $report['ticks'] = $this->countOn(DB::table('ratting_manager_ticks'), 'ticked_at', $corporationId, $days);
                $report['payouts'] = $this->countOn(DB::table('ratting_manager_ess_payouts'), 'paid_at', $corporationId, $days);
                $report['special_spawns'] = $this->spawns->detectFor($corporationId, $days);
                $report['sessions'] = $this->sessions->addFor($corporationId, $days);
                $report['daily_summaries'] = $this->summaries->rebuildCorporationDays($days, $corporationId);
                $report['character_days'] = $this->characterDays->rebuildCorporationDays($days, $corporationId);
                $report['monthly'] = $this->monthly->rebuildMonths(MonthlySummaryBuilder::monthsOf($days), null, $corporationId);
            }

            $report['dropped'] = $this->dropBehind($corporationId, $line);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $report + ['state' => $days === [] ? 'nothing_to_fill' : 'filled'];
    }

    /** @return string[] days from $from to the line the corporation has ticks or payouts on */
    private function daysWithRows(int $corporationId, Carbon $from, Carbon $line): array
    {
        $days = [];
        foreach ([['ratting_manager_ticks', 'ticked_at'], ['ratting_manager_ess_payouts', 'paid_at']] as [$table, $column]) {
            $rows = DB::table($table)
                ->where('corporation_id', $corporationId)
                ->where($column, '>=', $from)
                ->where($column, '<', $line)
                ->selectRaw("DATE({$column}) as day")
                ->distinct()
                ->pluck('day');

            foreach ($rows as $day) {
                $days[substr((string) $day, 0, 10)] = true;
            }
        }

        return array_keys($days);
    }

    private function countOn($query, string $column, int $corporationId, array $days): int
    {
        return $query->where('corporation_id', $corporationId)
            ->where(function ($q) use ($column, $days) {
                foreach ($days as $date) {
                    $q->orWhereBetween($column, [$date . ' 00:00:00', $date . ' 23:59:59']);
                }
            })
            ->count();
    }

    /** @return array{ticks: int, kills: int, payouts: int} */
    private function dropBehind(int $corporationId, Carbon $line): array
    {
        $gone = ['ticks' => 0, 'kills' => 0, 'payouts' => 0];

        do {
            $ids = DB::table('ratting_manager_ticks')->where('corporation_id', $corporationId)->where('ticked_at', '<', $line)
                ->limit(2000)->pluck('id')->all();
            if ($ids !== []) {
                $gone['kills'] += DB::table('ratting_manager_tick_kills')->whereIn('tick_id', $ids)->delete();
                $gone['ticks'] += DB::table('ratting_manager_ticks')->whereIn('id', $ids)->delete();
            }
        } while ($ids !== []);

        $gone['payouts'] = DB::table('ratting_manager_ess_payouts')->where('corporation_id', $corporationId)->where('paid_at', '<', $line)->delete();

        return $gone;
    }
}
