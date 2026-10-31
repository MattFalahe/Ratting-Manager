<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;

/**
 * Reading the day by day and month by month totals, for figures that are
 * summed over a stretch of time: a month's own rows where the whole month is
 * in the stretch, and daily rows for the days either side of it.
 *
 * Until the first import after these tables arrive has filled them, every
 * read goes to what they are built from, so the pages show the same figures
 * before and after that run.
 */
final class Summaries
{
    public const DAILY = 'ratting_manager_daily_summaries';
    public const MONTHLY = 'ratting_manager_monthly_summaries';

    private const COLUMNS = [
        'character_id', 'corporation_id', 'solar_system_id', 'ticks',
        'bounty_net', 'bounty_estimated', 'bounty_corp_tax', 'ess_net', 'ess_estimated', 'ess_corp_tax',
    ];

    /** Whether the monthly summaries and character days have been filled. */
    public static function ready(): bool
    {
        return (bool) app(Settings::class)->get('state.summaries_built');
    }

    /**
     * The scope's rows between two times, to group and sum over. Dates are
     * whole days, as the daily rows are.
     */
    public static function between(Scope $scope, Carbon $from, Carbon $to): Builder
    {
        $first = $from->toDateString();
        $last = $to->toDateString();

        $daily = $scope->apply(DB::table(self::DAILY));
        if (!self::ready()) {
            return $daily->whereBetween('date', [$first, $last]);
        }

        [$months, $edges] = self::split($first, $last);
        if ($months === []) {
            return $daily->whereBetween('date', [$first, $last]);
        }

        $rows = $scope->apply(DB::table(self::MONTHLY))->whereIn('month', $months)->select(self::COLUMNS);
        if ($edges !== []) {
            $rows->unionAll($daily->where(function ($query) use ($edges) {
                foreach ($edges as $edge) {
                    $query->orWhereBetween('date', $edge);
                }
            })->select(self::COLUMNS));
        }

        return DB::query()->fromSub($rows, 'summaries');
    }

    /**
     * The scope's rows from a month on with a `month` column, to group and
     * sum by month.
     *
     * @param string|null $fromMonth YYYY-MM, or null for every month
     */
    public static function months(Scope $scope, ?string $fromMonth = null): Builder
    {
        if (self::ready()) {
            $query = $scope->apply(DB::table(self::MONTHLY));

            return $fromMonth === null ? $query : $query->where('month', '>=', $fromMonth);
        }

        $query = $scope->apply(DB::table(self::DAILY))->select(self::COLUMNS)->selectRaw("DATE_FORMAT(date, '%Y-%m') as month");
        if ($fromMonth !== null) {
            $query->where('date', '>=', $fromMonth . '-01');
        }

        return DB::query()->fromSub($query, 'summaries');
    }

    /**
     * Which months lie wholly between two dates, and the runs of days left
     * over. No ticks are dated after today, so a month running up to today
     * counts as whole.
     *
     * @return array{0: string[], 1: array<int, array{0: string, 1: string}>}
     */
    private static function split(string $first, string $last): array
    {
        $today = Carbon::now('UTC')->toDateString();
        $months = [];
        $edges = [];

        $month = Carbon::createFromFormat('Y-m-d H:i:s', substr($first, 0, 7) . '-01 00:00:00', 'UTC');
        while ($month->toDateString() <= $last) {
            $start = $month->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();

            if ($first <= $start && ($last >= $end || $last >= $today)) {
                $months[] = $month->format('Y-m');
            } else {
                $edges[] = [max($first, $start), min($last, $end)];
            }

            $month->addMonthNoOverflow();
        }

        return [$months, $edges];
    }
}
