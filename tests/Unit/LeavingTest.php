<?php

namespace RattingManager\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use RattingManager\Services\Character\CurrentCorporations;
use RattingManager\Services\Character\Departures;

class LeavingTest extends TestCase
{
    private const OURS = 144534752;
    private const THEIRS = 98000001;

    private static function history(array ...$rows): array
    {
        return array_map(fn ($r) => ['corporation_id' => $r[0], 'start_date' => $r[1]], $rows);
    }

    public function test_the_next_rows_start_is_when_they_left(): void
    {
        $found = Departures::leftAfter(
            self::history([self::OURS, '2025-01-01 00:00:00'], [self::THEIRS, '2026-10-30 18:21:00']),
            self::OURS,
            '2026-10-23'
        );

        $this->assertSame(['left_at' => '2026-10-30 18:21:00', 'next_corporation_id' => self::THEIRS], $found);
    }

    public function test_still_there_has_no_leaving(): void
    {
        $this->assertNull(Departures::leftAfter(self::history([self::THEIRS, '2024-01-01 00:00:00'], [self::OURS, '2025-01-01 00:00:00']), self::OURS, '2026-10-23'));
    }

    public function test_a_history_not_caught_up_says_nothing(): void
    {
        $this->assertNull(Departures::leftAfter(self::history([self::OURS, '2025-01-01 00:00:00']), self::OURS, '2026-10-23'));
    }

    public function test_leaving_before_their_last_ratting_is_an_older_move(): void
    {
        $this->assertNull(Departures::leftAfter(
            self::history([self::OURS, '2025-01-01 00:00:00'], [self::THEIRS, '2026-03-01 00:00:00']),
            self::OURS,
            '2026-10-23'
        ));
    }

    public function test_leaving_on_the_day_of_their_last_tick_counts(): void
    {
        $this->assertNotNull(Departures::leftAfter(
            self::history([self::OURS, '2025-01-01 00:00:00'], [self::THEIRS, '2026-10-23 23:50:00']),
            self::OURS,
            '2026-10-23'
        ));
    }

    public function test_someone_who_came_back_and_went_again_left_the_last_time(): void
    {
        $found = Departures::leftAfter(
            self::history([self::OURS, '2025-01-01 00:00:00'], [self::THEIRS, '2026-03-01 00:00:00'], [self::OURS, '2026-06-01 00:00:00'], [self::THEIRS, '2026-10-30 18:21:00']),
            self::OURS,
            '2026-06-10'
        );

        $this->assertSame('2026-10-30 18:21:00', $found['left_at']);
    }

    private static function newest(?array $seat, ?array $lookup): ?int
    {
        return CurrentCorporations::newest($seat, $lookup, Carbon::parse('2026-10-20 12:00:00', 'UTC'))['corporation_id'] ?? null;
    }

    private static function seat(int $corporation, string $changed): array
    {
        return ['corporation_id' => $corporation, 'changed_at' => $changed];
    }

    private static function lookup(int $corporation, string $source, string $asked): array
    {
        return ['corporation_id' => $corporation, 'source' => $source, 'asked_at' => $asked];
    }

    public function test_whoever_answered_when_the_other_did_not(): void
    {
        $this->assertSame(1, self::newest(self::seat(1, '2026-10-01'), null));
        $this->assertSame(2, self::newest(null, self::lookup(2, 'esi', '2026-10-01')));
        $this->assertNull(self::newest(null, null));
    }

    public function test_esi_asked_after_seat_last_moved_outranks_it(): void
    {
        $this->assertSame(2, self::newest(self::seat(1, '2026-10-01'), self::lookup(2, 'esi', '2026-10-19')));
    }

    public function test_seat_moving_after_esi_was_asked_outranks_it(): void
    {
        $this->assertSame(1, self::newest(self::seat(1, '2026-10-19'), self::lookup(2, 'esi', '2026-10-01')));
    }

    public function test_the_slower_sources_wait_a_week_behind_seat(): void
    {
        foreach (['evewho', 'zkillboard'] as $source) {
            $this->assertSame(1, self::newest(self::seat(1, '2026-10-18'), self::lookup(2, $source, '2026-10-19')), $source);
            $this->assertSame(2, self::newest(self::seat(1, '2026-10-01'), self::lookup(2, $source, '2026-10-19')), $source);
        }
    }

    public function test_it_says_how_long_the_answer_has_stood(): void
    {
        $answer = CurrentCorporations::newest(self::seat(1, '2026-10-01 10:00:00'), null, Carbon::parse('2026-10-20', 'UTC'));

        $this->assertSame('2026-10-01 10:00:00', $answer['as_of']);
    }
}
