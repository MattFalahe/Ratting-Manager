<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The stretch of time a page is showing, with the stretch before it of the
 * same length for the change column. Everything is in EVE time.
 *
 * Besides the rolling periods there are calendar months (m-2026-09) and
 * custom ranges (r-20260801-20260915). The whole choice lives in the key,
 * so every link that passes the period on carries a range as it carries
 * "this_month".
 */
final class Period
{
    public const CHOICES = ['this_month', 'last_month', '30d', '90d', '12m'];

    /** The pages show a year at most, the year the first import brings in. */
    public const MAX_DAYS = 365;

    /** Calendar months offered besides the rolling periods; this month is "This month". */
    public const MONTHS = 11;

    public string $key;
    public Carbon $start;
    public Carbon $end;
    public Carbon $previousStart;
    public Carbon $previousEnd;

    private function __construct(string $key, Carbon $start, Carbon $end, Carbon $previousStart, Carbon $previousEnd)
    {
        $this->key = $key;
        $this->start = $start;
        $this->end = $end;
        $this->previousStart = $previousStart;
        $this->previousEnd = $previousEnd;
    }

    /**
     * The period a page was asked for. A custom range arrives from the picker
     * as period=custom with from and to dates, and becomes an r- key.
     */
    public static function fromRequest(Request $request, ?Carbon $now = null): self
    {
        $key = (string) $request->query('period', '');

        if ($key === 'custom') {
            $key = 'r-' . str_replace('-', '', (string) $request->query('from', '')) . '-' . str_replace('-', '', (string) $request->query('to', ''));
        }

        return self::from($key, $now);
    }

    public static function from(?string $key, ?Carbon $now = null): self
    {
        $now = ($now ?? Carbon::now('UTC'))->copy();

        if ($key !== null && ($period = self::calendarMonth($key, $now) ?? self::range($key, $now))) {
            return $period;
        }

        $key = in_array($key, self::CHOICES, true) ? $key : 'this_month';

        switch ($key) {
            case 'last_month':
                $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
                return new self($key, $start, $end, $prevStart, $prevStart->copy()->endOfMonth());

            case '30d':
            case '90d':
                $days = $key === '30d' ? 30 : 90;
                $start = $now->copy()->subDays($days - 1)->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                return new self($key, $start, $now, $prevEnd->copy()->subDays($days - 1)->startOfDay(), $prevEnd);

            case '12m':
                $start = $now->copy()->subMonthsNoOverflow(11)->startOfMonth();
                $prevEnd = $start->copy()->subSecond();
                return new self($key, $start, $now, $prevEnd->copy()->subMonthsNoOverflow(11)->startOfMonth(), $prevEnd);

            default:
                $start = $now->copy()->startOfMonth();
                $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
                // Compare like with like: the first N days of last month.
                $prevEnd = $prevStart->copy()->addSeconds($now->getTimestamp() - $start->getTimestamp());
                return new self('this_month', $start, $now, $prevStart, min($prevEnd, $prevStart->copy()->endOfMonth()));
        }
    }

    /** A week from Monday 00:00 to Sunday 23:59:59 EVE time, with the week before it. */
    public static function week(Carbon $anyDay): self
    {
        $start = $anyDay->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
        $prevStart = $start->copy()->subWeek();

        return new self('week', $start, $end, $prevStart, $prevStart->copy()->endOfWeek(Carbon::SUNDAY));
    }

    /** A calendar month, with the month before it. */
    public static function month(Carbon $anyDay): self
    {
        $start = $anyDay->copy()->startOfMonth();
        $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth();

        return new self('month', $start, $start->copy()->endOfMonth(), $prevStart, $prevStart->copy()->endOfMonth());
    }

    /**
     * One of the calendar months the picker offers, compared with the month
     * before it. Anything older is not a month the pages can show.
     */
    private static function calendarMonth(string $key, Carbon $now): ?self
    {
        if (!array_key_exists($key, self::monthChoices($now))) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d H:i:s', substr($key, 2) . '-01 00:00:00', 'UTC');
        $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth();

        return new self($key, $start, $start->copy()->endOfMonth(), $prevStart, $prevStart->copy()->endOfMonth());
    }

    /**
     * Any run of days within the last year, compared with as many days
     * before it. A start too far back or an end in the future is pulled in,
     * and the key is written back in the dates actually shown.
     */
    private static function range(string $key, Carbon $now): ?self
    {
        if (!preg_match('/^r-(\d{8})-(\d{8})$/', $key, $m)) {
            return null;
        }

        $days = [];
        foreach ([$m[1], $m[2]] as $ymd) {
            if (!checkdate((int) substr($ymd, 4, 2), (int) substr($ymd, 6, 2), (int) substr($ymd, 0, 4))) {
                return null;
            }
            $days[] = Carbon::createFromFormat('Ymd H:i:s', $ymd . ' 00:00:00', 'UTC');
        }
        [$from, $to] = $days[0]->lte($days[1]) ? $days : [$days[1], $days[0]];

        $earliest = self::earliest($now);
        $today = $now->copy()->startOfDay();
        $from = $from->max($earliest)->min($today);
        $to = $to->max($from)->min($today);

        $end = $to->copy()->endOfDay();
        if ($end->gt($now)) {
            $end = $now->copy();
        }
        $length = $from->diffInDays($to) + 1;
        $prevEnd = $from->copy()->subSecond();

        return new self('r-' . $from->format('Ymd') . '-' . $to->format('Ymd'), $from, $end, $from->copy()->subDays($length), $prevEnd);
    }

    /** The first day a custom range may start on. */
    public static function earliest(?Carbon $now = null): Carbon
    {
        return ($now ?? Carbon::now('UTC'))->copy()->subDays(self::MAX_DAYS - 1)->startOfDay();
    }

    /**
     * The calendar months the picker offers, last month first.
     *
     * @return array<string, string> key (m-2026-09) => label (September 2026)
     */
    public static function monthChoices(?Carbon $now = null): array
    {
        $month = ($now ?? Carbon::now('UTC'))->copy()->startOfMonth()->subMonthNoOverflow();
        $choices = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $choices['m-' . $month->format('Y-m')] = $month->format('F Y');
            $month->subMonthNoOverflow();
        }

        return $choices;
    }

    public function isMonth(): bool
    {
        return str_starts_with($this->key, 'm-');
    }

    public function isRange(): bool
    {
        return str_starts_with($this->key, 'r-');
    }

    public function label(): string
    {
        if ($this->isMonth()) {
            return $this->start->format('F Y');
        }

        if ($this->isRange()) {
            $sameYear = $this->start->year === $this->end->year;

            return trans('ratting-manager::common.period_range', [
                'from' => $this->start->format($sameYear ? 'j M' : 'j M Y'),
                'to' => $this->end->format('j M Y'),
            ]);
        }

        return trans('ratting-manager::common.period_' . $this->key);
    }

    public function cacheKey(): string
    {
        return $this->key . ':' . FigureCache::range($this->start, $this->end);
    }
}
