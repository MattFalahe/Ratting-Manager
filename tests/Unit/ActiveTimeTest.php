<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Ratting\ActiveTime;
use RattingManager\Services\Ratting\ActiveTimeCounter;

class ActiveTimeTest extends TestCase
{
    private const T = 1_800_000_000;

    public function test_no_ticks_no_time(): void
    {
        $this->assertSame(0, ActiveTime::minutes([]));
    }

    public function test_a_tick_stands_for_the_twenty_minutes_before_it(): void
    {
        $this->assertSame(20, ActiveTime::minutes([self::T]));
    }

    public function test_ticks_twenty_minutes_apart_add_up(): void
    {
        $this->assertSame(60, ActiveTime::minutes([self::T, self::T + 1200, self::T + 2400]));
    }

    public function test_alts_ratting_together_count_once(): void
    {
        $this->assertSame(20, ActiveTime::minutes([self::T, self::T, self::T]));
        $this->assertSame(25, ActiveTime::minutes([self::T, self::T + 300]));
    }

    public function test_a_pause_does_not_count(): void
    {
        $this->assertSame(40, ActiveTime::minutes([self::T, self::T + 7200]));
    }

    public function test_the_order_they_come_in_makes_no_difference(): void
    {
        $this->assertSame(
            ActiveTime::minutes([self::T, self::T + 300, self::T + 7200]),
            ActiveTime::minutes([self::T + 7200, self::T, self::T + 300])
        );
    }

    public function test_counting_as_they_arrive_gives_the_same_answer(): void
    {
        $times = [self::T, self::T, self::T + 300, self::T + 1500, self::T + 9000, self::T + 9001];
        $counter = new ActiveTimeCounter();
        foreach ($times as $t) {
            $counter->add($t);
        }

        $this->assertSame(ActiveTime::minutes($times), $counter->minutes());
    }
}
