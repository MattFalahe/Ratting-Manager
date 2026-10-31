<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Departures;
use RattingManager\Services\Configuration\Settings;
use Throwable;

/**
 * Deletes what has passed its time, so no table grows without end.
 *
 * Ticks, their kill rows, ESS payouts and the daily kill totals go at Keep raw
 * ticks, once their days are safely in the summaries. The character days hold
 * each day's ticks and kill lists, and the daily summaries its ISK, so every
 * page reads the same figures afterwards. A year on those go too, with
 * everything else tied to the day, since no page shows further back. The month
 * by month totals stay a year longer, for the change on the 12 months before.
 *
 * Rebuilding a day deletes its rows and writes them again from the ticks, so a
 * rebuild of a day whose ticks have gone would empty it, and importing it again
 * would count it from whatever journal rows SeAT still has. state.raw_kept_from
 * is the line nothing crosses. It is written before anything is deleted, so a
 * run stopped part way leaves the days already protected.
 */
class RawTicks
{
    /** Further back than the nightly 31-day read and Diagnostics' 30-day comparisons reach. */
    public const MIN_DAYS = 45;

    /** No page shows more than a year, so nothing is kept or imported from before it. */
    public const KEEP_DAYS = Period::MAX_DAYS;

    /** A year of months and the year before it, so the last 12 can be compared with the 12 before them. */
    public const KEEP_MONTHS = 25;

    /** How far back a restore reaches: no page shows more than a year. */
    public const RESTORE_DAYS = self::KEEP_DAYS;

    private const CHUNK = 2000;

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /** The first day ticks are still held for, or null if none have been dropped. */
    public static function keptFrom(): ?Carbon
    {
        $date = app(Settings::class)->get('state.raw_kept_from');

        return $date ? Carbon::parse($date, 'UTC')->startOfDay() : null;
    }

    /** The first day anything is kept for. */
    public static function oldestDay(): Carbon
    {
        return now()->subDays(self::KEEP_DAYS)->startOfDay();
    }

    /** The first day raw rows can be counted on: the line, or a year back when that is later. */
    public static function firstHeld(): Carbon
    {
        $kept = self::keptFrom();
        $oldest = self::oldestDay();

        return $kept !== null && $kept->gt($oldest) ? $kept : $oldest;
    }

    /** This time, or the first day raw rows are held for if that is later. */
    public static function clamp(Carbon $since): Carbon
    {
        $first = self::firstHeld();

        return $since->lt($first) ? $first : $since;
    }

    /**
     * @param string[] $dates Y-m-d
     * @return string[] the ones raw rows are still held for
     */
    public static function heldDates(array $dates): array
    {
        $first = self::firstHeld()->toDateString();

        return array_values(array_filter($dates, fn ($date) => $date >= $first));
    }

    /**
     * @return array{state: string, days?: int, until?: string, ticks?: int, kills?: int, payouts?: int, daily_kills?: int, held_by?: ?string, at?: string, older_than_a_year?: array<string, int>}
     *         state is off, not_ready, build_owed, nothing or dropped; a dry run reports would_drop and deletes nothing.
     *         older_than_a_year says what went for being past what any page shows, whatever Keep raw ticks is set to.
     */
    public function drop(bool $dryRun = false): array
    {
        if (!Summaries::ready()) {
            return ['state' => 'not_ready'];
        }
        // A tick can have moved with its day's count unchanged, which the day
        // by day check below cannot see, so nothing goes until the build is done.
        if ($this->settings->get('state.build_owed') !== null) {
            return ['state' => 'build_owed'];
        }

        $history = $this->olderThanAYear($dryRun);
        $days = (int) $this->settings->get('data.raw_tick_days');

        if ($days === 0) {
            return ['state' => 'off', 'older_than_a_year' => $history];
        }

        $days = max($days, self::MIN_DAYS);
        $cutoff = now()->subDays($days)->startOfDay();
        $heldBy = $this->firstUnsafeDay($cutoff);
        $until = $heldBy !== null ? Carbon::parse($heldBy, 'UTC')->startOfDay() : $cutoff;

        $report = ['days' => $days, 'until' => $until->toDateString(), 'held_by' => $heldBy, 'at' => now()->toDateTimeString(), 'older_than_a_year' => $history];

        $counts = [
            'ticks' => DB::table('ratting_manager_ticks')->where('ticked_at', '<', $until)->count(),
            'payouts' => DB::table('ratting_manager_ess_payouts')->where('paid_at', '<', $until)->count(),
            'daily_kills' => DB::table('ratting_manager_daily_kills')->where('date', '<', $until->toDateString())->count(),
        ];

        if (array_sum($counts) === 0) {
            return $report + ['state' => 'nothing', 'kills' => 0] + $counts;
        }

        if ($dryRun) {
            return $report + ['state' => 'would_drop', 'kills' => $this->killRowsBefore($until)] + $counts;
        }

        $this->settings->set('state.raw_kept_from', $until->toDateString());

        $report += ['state' => 'dropped'] + $this->delete($until);
        $this->settings->set('state.last_raw_drop', $report);

        return $report;
    }

    /**
     * Everything from before what any page can show, whatever Keep raw ticks
     * is set to. Nothing needs checking first: these days are never read again.
     *
     * @return array<string, int> what went, by what it was, leaving out what had nothing to go
     */
    private function olderThanAYear(bool $dryRun): array
    {
        $oldest = self::oldestDay();
        $date = $oldest->toDateString();

        $queries = [
            'payouts' => DB::table('ratting_manager_ess_payouts')->where('paid_at', '<', $oldest),
            'daily_kills' => DB::table('ratting_manager_daily_kills')->where('date', '<', $date),
            'daily_summaries' => DB::table(Summaries::DAILY)->where('date', '<', $date),
            'character_days' => DB::table(CharacterDays::TABLE)->where('date', '<', $date),
            'sessions' => DB::table('ratting_manager_sessions')->where('ended_at', '<', $oldest),
            'special_spawns' => DB::table('ratting_manager_special_spawns')->where('ticked_at', '<', $oldest),
            'tax_rates' => DB::table('ratting_manager_corp_tax_rates')->where('date', '<', $date),
            'departures' => DB::table(Departures::TABLE)->where('left_at', '<', $oldest),
            'monthly_summaries' => DB::table(Summaries::MONTHLY)->where('month', '<', now()->subMonthsNoOverflow(self::KEEP_MONTHS - 1)->format('Y-m')),
        ];

        if ($dryRun) {
            $gone = [
                'ticks' => DB::table('ratting_manager_ticks')->where('ticked_at', '<', $oldest)->count(),
                'kills' => $this->killRowsBefore($oldest),
            ];
            foreach ($queries as $name => $query) {
                $gone[$name] = $query->count();
            }

            return array_filter($gone);
        }

        $gone = $this->deleteTicks($oldest);
        foreach ($queries as $name => $query) {
            $gone[$name] = $this->inBatches($query);
        }

        $gone = array_filter($gone);
        if ($gone !== []) {
            $this->settings->set('state.last_year_drop', $gone + ['at' => now()->toDateTimeString()]);
        }

        return $gone;
    }

    private function killRowsBefore(Carbon $until): int
    {
        return DB::table('ratting_manager_tick_kills as k')
            ->join('ratting_manager_ticks as t', 't.id', '=', 'k.tick_id')
            ->where('t.ticked_at', '<', $until)
            ->count();
    }

    /**
     * Import dropped days again from SeAT's journals, moving the line back.
     *
     * The real import and rebuild run inside one transaction, so what a look
     * ahead reports is what the code really does, and rolling back leaves
     * nothing behind. A day is rebuilt whole for every corporation, so a day
     * that would come back with fewer ticks or less income than its summaries
     * hold, because SeAT no longer has its journal rows or its corporation is
     * no longer tracked, stops the whole restore rather than lose them.
     *
     * @param bool $apply false looks ahead and always rolls back
     * @return array<string, mixed> state is nothing_dropped, no_corporations, not_before_line, too_old, would_lose, broken, preview or restored
     */
    public function restore(Carbon $from, bool $apply): array
    {
        $from = $from->copy()->startOfDay();
        $line = self::keptFrom();

        if ($line === null) {
            return ['state' => 'nothing_dropped'];
        }
        if ($this->settings->configuredCorporations() === []) {
            return ['state' => 'no_corporations'];
        }
        if ($from->gte($line)) {
            return ['state' => 'not_before_line', 'line' => $line->toDateString()];
        }
        $oldest = now()->subDays(self::RESTORE_DAYS)->startOfDay();
        if ($from->lt($oldest)) {
            return ['state' => 'too_old', 'oldest' => $oldest->toDateString()];
        }

        $counts = fn () => [
            'ticks' => DB::table('ratting_manager_ticks')->count(),
            'kills' => DB::table('ratting_manager_tick_kills')->count(),
            'ess' => DB::table('ratting_manager_ess_payouts')->count(),
            'sessions' => DB::table('ratting_manager_sessions')->count(),
            'spawns' => DB::table('ratting_manager_special_spawns')->count(),
        ];

        DB::beginTransaction();

        try {
            $before = $this->byCorporationDay($from);
            $totals = $counts();

            $this->settings->set('state.raw_kept_from', $from->toDateString());
            $run = app(Pipeline::class)->run($from->copy(), null, false);

            $after = $this->byCorporationDay($from);
            $report = $this->compare($from, $line, $before, $after, $totals, $counts());
            $report['keep_days'] = (int) $this->settings->get('data.raw_tick_days');
            $report['rows_failed'] = 0;
            foreach (['corporation_journal', 'character_journal'] as $journal) {
                $report['rows_failed'] += $run[$journal]['bounty']['failed'] + $run[$journal]['ess']['failed'];
            }

            if ($report['loses'] !== []) {
                $report['state'] = 'would_lose';
            } elseif ($report['disagree'] !== []) {
                $report['state'] = 'broken';
            } else {
                $report['state'] = $apply ? 'restored' : 'preview';
            }

            if ($report['state'] !== 'restored') {
                DB::rollBack();
                $this->settings->reload();

                return $report;
            }

            $report['at'] = now()->toDateTimeString();
            $this->settings->set('state.last_raw_restore', array_intersect_key($report, array_flip(['from', 'line', 'added', 'at'])));
            DB::commit();

            return $report;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->settings->reload();

            throw $e;
        }
    }

    /** @return array<string, array{ticks: int, income: float}> keyed date:corporation, from the daily summaries */
    private function byCorporationDay(Carbon $from): array
    {
        $rows = [];
        foreach (DB::table(Summaries::DAILY)->where('date', '>=', $from->toDateString())
            ->groupBy('date', 'corporation_id')
            ->selectRaw('date, corporation_id, SUM(ticks) as ticks, SUM(bounty_net + ess_net) as income')
            ->get() as $r) {
            $rows[substr((string) $r->date, 0, 10) . ':' . (int) $r->corporation_id] = ['ticks' => (int) $r->ticks, 'income' => (float) $r->income];
        }

        return $rows;
    }

    private function compare(Carbon $from, Carbon $line, array $before, array $after, array $totalsBefore, array $totalsAfter): array
    {
        $blank = fn () => ['ticks_before' => 0, 'ticks_after' => 0, 'income_before' => 0.0, 'income_after' => 0.0];
        $months = [];
        $corporations = [];
        $loses = [];
        $gained = 0;
        $incomeMoved = 0;

        foreach (array_keys($before + $after) as $key) {
            [$date, $corporation] = explode(':', $key);
            $b = $before[$key] ?? ['ticks' => 0, 'income' => 0.0];
            $a = $after[$key] ?? ['ticks' => 0, 'income' => 0.0];

            foreach ([substr($date, 0, 7) => &$months, (int) $corporation => &$corporations] as $k => &$bucket) {
                $bucket[$k] ??= $blank();
                $bucket[$k]['ticks_before'] += $b['ticks'];
                $bucket[$k]['ticks_after'] += $a['ticks'];
                $bucket[$k]['income_before'] += $b['income'];
                $bucket[$k]['income_after'] += $a['income'];
            }
            unset($bucket);

            // Payouts behind the line have gone as ticks have, so a day can come
            // back with every tick and short of income. Cents move when a tick
            // pairs, which is why this leaves a margin.
            if ($a['ticks'] < $b['ticks'] || $a['income'] < $b['income'] * 0.99) {
                $loses[] = ['date' => $date, 'corporation' => (int) $corporation, 'before' => $b['ticks'], 'after' => $a['ticks'], 'income_before' => $b['income'], 'income_after' => $a['income']];
            } elseif ($a['ticks'] > $b['ticks']) {
                $gained++;
            } elseif (abs($a['income'] - $b['income']) > 0.01) {
                $incomeMoved++;
            }
        }
        ksort($months);
        usort($loses, fn ($x, $y) => [$x['date'], $x['corporation']] <=> [$y['date'], $y['corporation']]);

        $added = [];
        foreach ($totalsAfter as $name => $count) {
            $added[$name] = $count - $totalsBefore[$name];
        }

        return [
            'from' => $from->toDateString(),
            'line' => $line->toDateString(),
            'months' => $months,
            'corporations' => $corporations,
            'loses' => $loses,
            'days_gaining' => $gained,
            'days_income_moved' => $incomeMoved,
            'added' => $added,
            'disagree' => $this->daysThatDisagree($from),
        ];
    }

    /** @return string[] days from here on whose character days or daily summaries differ from the ticks */
    private function daysThatDisagree(Carbon $from): array
    {
        $ticks = $this->byDay(DB::table('ratting_manager_ticks')->where('ticked_at', '>=', $from)
            ->groupBy('day')->selectRaw('DATE(ticked_at) as day, COUNT(*) as n'));
        $kept = $this->byDay(DB::table(CharacterDays::TABLE)->where('date', '>=', $from->toDateString())
            ->groupBy('date')->selectRaw('date as day, SUM(tick_count) as n'));
        $daily = $this->byDay(DB::table(Summaries::DAILY)->where('date', '>=', $from->toDateString())
            ->groupBy('date')->selectRaw('date as day, SUM(ticks) as n'));

        // A day with no ticks has no rows to disagree with.
        $zero = fn ($counts) => array_filter($counts, fn ($n) => $n > 0);
        $days = array_keys(array_diff_assoc($ticks, $zero($kept)) + array_diff_assoc($zero($kept), $ticks) + array_diff_assoc($ticks, $zero($daily)) + array_diff_assoc($zero($daily), $ticks));
        sort($days);

        return $days;
    }

    /**
     * The oldest day before the cutoff that the summaries cannot yet stand in
     * for: its counts disagree, or a tick or payout still waits on a tax rate
     * and would never get its income. Nothing from that day on is dropped.
     */
    private function firstUnsafeDay(Carbon $cutoff): ?string
    {
        $kept = $this->byDay(DB::table(CharacterDays::TABLE)->where('date', '<', $cutoff->toDateString())
            ->groupBy('date')->selectRaw('date as day, SUM(tick_count) as n'));
        $daily = $this->byDay(DB::table(Summaries::DAILY)->where('date', '<', $cutoff->toDateString())
            ->groupBy('date')->selectRaw('date as day, SUM(ticks) as n'));

        $ticks = DB::table('ratting_manager_ticks')
            ->where('ticked_at', '<', $cutoff)
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw("DATE(ticked_at) as day, COUNT(*) as n, SUM(kind = 'corporation' AND tax_rate IS NULL) as waiting")
            ->get();

        $unsafe = null;
        foreach ($ticks as $row) {
            $day = substr((string) $row->day, 0, 10);
            $n = (int) $row->n;

            if (($kept[$day] ?? 0) !== $n || ($daily[$day] ?? 0) !== $n || (int) $row->waiting > 0) {
                $unsafe = $day;
                break;
            }
        }

        $payouts = $this->firstUnsafePayoutDay($cutoff);

        return $payouts !== null && ($unsafe === null || $payouts < $unsafe) ? $payouts : $unsafe;
    }

    /**
     * The same for payouts. Days behind the line are left out: they can never
     * be built again, so the rows still on file there have no use left.
     */
    private function firstUnsafePayoutDay(Carbon $cutoff): ?string
    {
        $line = self::keptFrom();

        $held = DB::table(Summaries::DAILY)->where('date', '<', $cutoff->toDateString());
        $payouts = DB::table('ratting_manager_ess_payouts')->where('paid_at', '<', $cutoff);
        if ($line !== null) {
            $held->where('date', '>=', $line->toDateString());
            $payouts->where('paid_at', '>=', $line);
        }

        $summed = $held->groupBy('date')->selectRaw('date as day, SUM(ess_net) as net')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->day, 0, 10) => (float) $r->net])
            ->all();

        $days = $payouts->groupBy('day')->orderBy('day')
            ->selectRaw("DATE(paid_at) as day, SUM(net_amount) as net, SUM(kind = 'corporation' AND tax_rate IS NULL) as waiting")
            ->get();

        foreach ($days as $row) {
            $day = substr((string) $row->day, 0, 10);

            if (abs(($summed[$day] ?? 0.0) - (float) $row->net) >= 0.01 || (int) $row->waiting > 0) {
                return $day;
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function byDay($query): array
    {
        return $query->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->day, 0, 10) => (int) $r->n])
            ->all();
    }

    private function delete(Carbon $until): array
    {
        return $this->deleteTicks($until) + [
            'payouts' => $this->inBatches(DB::table('ratting_manager_ess_payouts')->where('paid_at', '<', $until)),
            'daily_kills' => $this->inBatches(DB::table('ratting_manager_daily_kills')->where('date', '<', $until->toDateString())),
        ];
    }

    /** In small batches, so the import and the alerts are not held up behind one long delete. */
    private function inBatches($query): int
    {
        $deleted = 0;

        do {
            $gone = (clone $query)->limit(self::CHUNK)->delete();
            $deleted += $gone;
        } while ($gone > 0);

        return $deleted;
    }

    /** @return array{ticks: int, kills: int} */
    private function deleteTicks(Carbon $until): array
    {
        $deleted = ['ticks' => 0, 'kills' => 0];

        do {
            $ids = DB::table('ratting_manager_ticks')->where('ticked_at', '<', $until)->limit(self::CHUNK)->pluck('id')->all();

            if ($ids !== []) {
                DB::transaction(function () use ($ids, &$deleted) {
                    $deleted['kills'] += DB::table('ratting_manager_tick_kills')->whereIn('tick_id', $ids)->delete();
                    $deleted['ticks'] += DB::table('ratting_manager_ticks')->whereIn('id', $ids)->delete();
                });
            }
        } while ($ids !== []);

        return $deleted;
    }
}
