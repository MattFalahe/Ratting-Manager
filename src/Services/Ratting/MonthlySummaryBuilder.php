<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Adds the daily summaries up per month. A month is rebuilt whole from its
 * days whenever any of them changes, so the two tables always agree.
 */
class MonthlySummaryBuilder
{
    private const SUMS = ['ticks', 'bounty_net', 'bounty_estimated', 'bounty_corp_tax', 'ess_net', 'ess_estimated', 'ess_corp_tax'];

    /** @return string[] every month with a daily summary, oldest first */
    public static function allMonths(): array
    {
        return DB::table('ratting_manager_daily_summaries')
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month")
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->all();
    }

    /** @return string[] the months these days fall in */
    public static function monthsOf(array $dates): array
    {
        $months = array_values(array_unique(array_map(fn ($date) => substr($date, 0, 7), $dates)));
        sort($months);

        return $months;
    }

    /**
     * @param string[] $months YYYY-MM
     * @param int|null $corporationId one corporation's rows alone, every other corporation's left as they are
     * @return int rows written
     */
    public function rebuildMonths(array $months, ?Progress $progress = null, ?int $corporationId = null): int
    {
        $progress ??= Progress::none();
        $written = 0;

        foreach ($months as $month) {
            $written += $this->rebuildMonth($month, $corporationId);
            $progress->advance();
        }

        return $written;
    }

    private function rebuildMonth(string $month, ?int $corporationId = null): int
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00', 'UTC');
        $ours = fn ($query) => $corporationId === null ? $query : $query->where('corporation_id', $corporationId);

        // A month the year has started running out of has lost days from the
        // daily rows, so adding it up again would shrink it. It stands as built.
        if ($start->lt(RawTicks::oldestDay()) && $ours(DB::table(Summaries::MONTHLY)->where('month', $month))->exists()) {
            return 0;
        }

        $now = now()->format('Y-m-d H:i:s');

        $sums = implode(', ', array_map(fn ($column) => "SUM({$column}) as {$column}", self::SUMS));
        $rows = $ours(DB::table('ratting_manager_daily_summaries')
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]))
            ->groupBy('character_id', 'corporation_id', 'solar_system_id')
            ->selectRaw("? as month, character_id, corporation_id, solar_system_id, {$sums}, ? as created_at, ? as updated_at", [$month, $now, $now]);

        return DB::transaction(function () use ($month, $rows, $ours) {
            $ours(DB::table(Summaries::MONTHLY)->where('month', $month))->delete();

            return DB::table(Summaries::MONTHLY)->insertUsing(
                array_merge(['month', 'character_id', 'corporation_id', 'solar_system_id'], self::SUMS, ['created_at', 'updated_at']),
                $rows
            );
        });
    }
}
