<?php

namespace RattingManager\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use RattingManager\Services\Character\Departures;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Ratting\RattingMetrics;

class PartlyLeftTest extends TestCase
{
    private const OURS = 144534752;
    private const OTHER_TRACKED = 98000002;
    private const THEIRS = 98000001;

    private static function move(string $leftAt, string $source = 'seat'): object
    {
        return (object) ['left_at' => $leftAt, 'source' => $source];
    }

    public function test_a_character_somewhere_else_with_its_move_on_file_has_left(): void
    {
        $out = Players::departedFrom([11 => self::move('2026-11-05 18:00:00')], [11 => self::THEIRS], [self::OURS]);

        $this->assertSame([11 => ['character_id' => 11, 'left_at' => '2026-11-05 18:00:00', 'left_exact' => true]], $out);
    }

    public function test_a_character_that_came_back_has_not_left(): void
    {
        $this->assertSame([], Players::departedFrom([11 => self::move('2026-09-01 00:00:00')], [11 => self::OURS], [self::OURS]));
    }

    public function test_moving_to_another_tracked_corporation_is_not_leaving(): void
    {
        $this->assertSame([], Players::departedFrom([11 => self::move('2026-11-05 18:00:00')], [11 => self::OTHER_TRACKED], [self::OURS, self::OTHER_TRACKED]));
    }

    public function test_nobody_knowing_where_they_are_is_not_leaving(): void
    {
        $this->assertSame([], Players::departedFrom([11 => self::move('2026-11-05 18:00:00')], [], [self::OURS]));
    }

    public function test_a_move_only_noticed_is_not_exact(): void
    {
        $out = Players::departedFrom([11 => self::move('2026-11-05 18:00:00', Departures::SEEN)], [11 => self::THEIRS], [self::OURS]);

        $this->assertFalse($out[11]['left_exact']);
    }

    public function test_a_player_who_left_is_gone_when_they_left(): void
    {
        $this->assertSame(
            ['gone' => true, 'gone_at' => '2026-11-05 18:00:00'],
            RattingMetrics::goneBy(['left' => true, 'left_at' => '2026-11-05 18:00:00', 'characters' => [11], 'alts_left' => []])
        );
    }

    public function test_one_alt_leaving_while_another_ratted_is_not_gone(): void
    {
        $row = ['left' => false, 'characters' => [11, 12], 'alts_left' => [['character_id' => 11, 'left_at' => '2026-11-05 18:00:00']]];

        $this->assertFalse(RattingMetrics::goneBy($row)['gone']);
    }

    public function test_every_character_that_ratted_leaving_is_gone_at_the_last_of_them(): void
    {
        $row = ['left' => false, 'characters' => [11, 12], 'alts_left' => [
            ['character_id' => 11, 'left_at' => '2026-11-02 10:00:00'],
            ['character_id' => 12, 'left_at' => '2026-11-05 18:00:00'],
        ]];

        $this->assertSame(['gone' => true, 'gone_at' => '2026-11-05 18:00:00'], RattingMetrics::goneBy($row));
    }

    public function test_someone_still_here_stays_listed(): void
    {
        $this->assertTrue(RattingMetrics::stillListed(['gone' => false, 'gone_at' => null], Carbon::parse('2026-11-01', 'UTC')));
    }

    public function test_a_leaver_stays_listed_through_the_month_they_left(): void
    {
        $this->assertTrue(RattingMetrics::stillListed(['gone' => true, 'gone_at' => '2026-11-01 00:00:00'], Carbon::parse('2026-11-01', 'UTC')));
        $this->assertTrue(RattingMetrics::stillListed(['gone' => true, 'gone_at' => '2026-11-30 23:59:59'], Carbon::parse('2026-11-01', 'UTC')));
    }

    public function test_a_leaver_drops_off_once_that_month_is_over(): void
    {
        $this->assertFalse(RattingMetrics::stillListed(['gone' => true, 'gone_at' => '2026-10-31 23:59:59'], Carbon::parse('2026-11-01', 'UTC')));
    }

    public function test_a_leaver_whose_time_is_still_being_looked_up_stays(): void
    {
        $this->assertTrue(RattingMetrics::stillListed(['gone' => true, 'gone_at' => null], Carbon::parse('2026-11-01', 'UTC')));
    }
}
