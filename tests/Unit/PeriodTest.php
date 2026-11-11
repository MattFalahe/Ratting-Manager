<?php

namespace RattingManager\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RawTicks;

class PeriodTest extends TestCase
{
    private static function at(string $when): Carbon
    {
        return Carbon::parse($when, 'UTC');
    }

    public function test_this_month_compares_with_as_many_days_of_the_last(): void
    {
        $p = Period::from('this_month', self::at('2026-10-15 12:00:00'));

        $this->assertSame('2026-10-01 00:00:00', $p->start->toDateTimeString());
        $this->assertSame('2026-10-15 12:00:00', $p->end->toDateTimeString());
        $this->assertSame('2026-09-01 00:00:00', $p->previousStart->toDateTimeString());
        $this->assertSame('2026-09-15 12:00:00', $p->previousEnd->toDateTimeString());
    }

    public function test_the_31st_does_not_run_past_a_shorter_month(): void
    {
        $p = Period::from('this_month', self::at('2026-10-31 12:00:00'));

        $this->assertSame('2026-09-30 23:59:59', $p->previousEnd->toDateTimeString());
    }

    public function test_last_month_across_a_new_year(): void
    {
        $p = Period::from('last_month', self::at('2027-01-10 00:00:00'));

        $this->assertSame('2026-12-01', $p->start->toDateString());
        $this->assertSame('2026-12-31', $p->end->toDateString());
        $this->assertSame('2026-11-01', $p->previousStart->toDateString());
    }

    public function test_rolling_periods_count_today(): void
    {
        $p = Period::from('30d', self::at('2026-10-31 12:00:00'));

        $this->assertSame('2026-10-02', $p->start->toDateString());
        $this->assertSame('2026-09-02', $p->previousStart->toDateString());
        $this->assertSame('2026-10-01', $p->previousEnd->toDateString());
    }

    public function test_the_last_12_months_are_whole_months_with_12_whole_months_before(): void
    {
        $p = Period::from('12m', self::at('2026-10-31 12:00:00'));

        $this->assertSame('2025-11-01', $p->start->toDateString());
        $this->assertSame('2024-11-01', $p->previousStart->toDateString());
        $this->assertSame('2025-10-31', $p->previousEnd->toDateString());
    }

    public function test_anything_unknown_is_this_month(): void
    {
        $this->assertSame('this_month', Period::from('nonsense', self::at('2026-10-15'))->key);
        $this->assertSame('this_month', Period::from(null, self::at('2026-10-15'))->key);
        $this->assertSame('this_month', Period::from('m-2020-01', self::at('2026-10-15'))->key);
        $this->assertSame('this_month', Period::from('r-20261301-20261302', self::at('2026-10-15'))->key);
    }

    public function test_a_range_is_pulled_in_to_the_year_and_written_back(): void
    {
        $now = self::at('2026-10-15 12:00:00');
        $p = Period::from('r-20200101-20300101', $now);

        $this->assertSame('r-20251016-20261015', $p->key);
        $this->assertSame('2025-10-16', $p->start->toDateString());
        $this->assertSame('2026-10-15 12:00:00', $p->end->toDateTimeString());
    }

    public function test_a_range_given_backwards_is_put_right(): void
    {
        $this->assertSame('r-20261001-20261005', Period::from('r-20261005-20261001', self::at('2026-10-15'))->key);
    }

    public function test_the_month_picker_offers_eleven_months_before_this_one(): void
    {
        $choices = Period::monthChoices(self::at('2026-10-15'));

        $this->assertCount(11, $choices);
        $this->assertSame('m-2026-09', array_key_first($choices));
        $this->assertSame('m-2025-11', array_key_last($choices));
    }

    /**
     * The nightly clear-out goes by this: nothing a page can ask for may reach
     * back past what is kept, on any day, leap years and month ends included.
     */
    public function test_no_period_on_offer_reaches_before_what_is_kept(): void
    {
        for ($day = self::at('2027-12-20'); $day->lte(self::at('2029-01-10')); $day->addDay()) {
            $oldest = $day->copy()->subDays(RawTicks::KEEP_DAYS)->startOfDay();

            $this->assertTrue(Period::from('12m', $day)->start->gte($oldest), '12m on ' . $day->toDateString());
            $this->assertTrue(Period::earliest($day)->gte($oldest), 'custom range on ' . $day->toDateString());

            $key = array_key_last(Period::monthChoices($day));
            $this->assertTrue(Period::from($key, $day)->start->gte($oldest), $key . ' on ' . $day->toDateString());
        }
    }

    /** The month rows have to reach the 12 months before the last 12, and the month before the oldest month on offer. */
    public function test_the_month_rows_reach_every_comparison(): void
    {
        for ($day = self::at('2027-12-20'); $day->lte(self::at('2029-01-10')); $day->addDay()) {
            $oldestMonth = $day->copy()->subMonthsNoOverflow(RawTicks::KEEP_MONTHS - 1)->format('Y-m');

            $this->assertTrue(Period::from('12m', $day)->previousStart->format('Y-m') >= $oldestMonth, '12m on ' . $day->toDateString());
        }
    }
}
