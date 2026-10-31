<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Import\ReasonParser;

class ReasonParserTest extends TestCase
{
    public function test_reads_a_kill_list_sorted_by_type(): void
    {
        $this->assertSame([60317 => 2, 60580 => 2], ReasonParser::kills('60580: 2,60317: 2'));
    }

    public function test_keeps_what_came_before_a_list_cut_short(): void
    {
        $this->assertSame([23321 => 1, 23323 => 4], ReasonParser::kills('23323: 4,23321: 1,...'));
    }

    public function test_the_same_type_twice_adds_up(): void
    {
        $this->assertSame([100 => 5], ReasonParser::kills('100: 2,100: 3'));
    }

    public function test_nothing_to_read(): void
    {
        $this->assertSame([], ReasonParser::kills(null));
        $this->assertSame([], ReasonParser::kills(''));
        $this->assertSame([], ReasonParser::kills('no kills here'));
        $this->assertSame([], ReasonParser::kills('100: 0'));
    }

    public function test_fleet_members_share_a_kill_group(): void
    {
        $kills = ReasonParser::kills('60580: 2,60317: 2');
        $same = ReasonParser::kills('60317: 2,60580: 2');

        $this->assertSame(
            ReasonParser::killGroup('2026-10-01 12:00:00', 30003841, $kills),
            ReasonParser::killGroup('2026-10-01 12:00:00', 30003841, $same)
        );
    }

    public function test_another_time_or_system_or_list_is_another_group(): void
    {
        $kills = [100 => 1];
        $group = ReasonParser::killGroup('2026-10-01 12:00:00', 1, $kills);

        $this->assertNotSame($group, ReasonParser::killGroup('2026-10-01 12:20:00', 1, $kills));
        $this->assertNotSame($group, ReasonParser::killGroup('2026-10-01 12:00:00', 2, $kills));
        $this->assertNotSame($group, ReasonParser::killGroup('2026-10-01 12:00:00', 1, [100 => 2]));
    }

    public function test_no_kills_is_no_group(): void
    {
        $this->assertNull(ReasonParser::killGroup('2026-10-01 12:00:00', 1, []));
    }
}
