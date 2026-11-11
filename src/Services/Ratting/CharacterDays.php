<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Reading a character's day of ticks back from ratting_manager_character_days.
 *
 * Each row's `ticks` is a JSON list with one entry per tick, oldest first:
 *     [seconds into the day, system, net amount, rats, kill group, kind]
 * and `kills` maps each kill group to its list, {group: {npc type: quantity}}.
 *
 * Exact seconds are kept rather than which minutes were ratted, so time counts
 * the same here as from the ticks themselves: the same counting runs over the
 * same values. The kill group is cut to its first characters, which is enough
 * to tell one apart from any other on the same day.
 */
final class CharacterDays
{
    public const TABLE = 'ratting_manager_character_days';

    public const KILL_GROUP_LENGTH = 12;

    /** A tick's kind as written in a row. */
    public const KINDS = ['paired' => 'p', 'character' => 'c', 'corporation' => 'o'];

    /** The scope's ticks between two times, oldest first, shaped like rows of the tick table. */
    public static function ticks(Scope $scope, Carbon $from, Carbon $to): LazyCollection
    {
        return self::read($scope->apply(DB::table(self::TABLE)), $from, $to);
    }

    /**
     * Rats killed per NPC type between two times, each kill group counted
     * once however many of the scope's characters shared it.
     *
     * @return array<int, int> npc_type_id => quantity, lowest type first
     */
    public static function killsByType(Scope $scope, Carbon $from, Carbon $to): array
    {
        [$fromSecond, $toSecond] = [self::second($from), self::second($to)];

        $rows = $scope->apply(DB::table(self::TABLE))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select(['date', 'ticks', 'kills'])
            ->cursor();

        $quantities = [];
        $seen = [];
        foreach ($rows as $row) {
            $lists = json_decode($row->kills, true);
            if (!$lists) {
                continue;
            }

            $date = substr((string) $row->date, 0, 10);
            $dayStart = strtotime($date . ' 00:00:00 UTC');
            foreach (json_decode($row->ticks, true) as $tick) {
                $group = $tick[4];
                $second = $dayStart + $tick[0];
                if ($group === null || !isset($lists[$group]) || $second < $fromSecond || $second > $toSecond || isset($seen[$date][$group])) {
                    continue;
                }

                $seen[$date][$group] = true;
                foreach ($lists[$group] as $type => $quantity) {
                    $quantities[$type] = ($quantities[$type] ?? 0) + $quantity;
                }
            }
        }

        ksort($quantities);

        return $quantities;
    }

    /**
     * When each character's last tick for these corporations paid.
     *
     * @param int[] $characters
     * @param int[] $corporations
     * @return array<int, string> character_id => Y-m-d H:i:s
     */
    public static function lastTicks(array $characters, array $corporations): array
    {
        $newest = DB::table(self::TABLE)
            ->whereIn('character_id', $characters ?: [0])
            ->whereIn('corporation_id', $corporations ?: [0])
            ->groupBy('character_id')
            ->selectRaw('character_id, MAX(date) as day')
            ->pluck('day', 'character_id')
            ->all();

        if ($newest === []) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->whereIn('character_id', array_keys($newest))
            ->whereIn('corporation_id', $corporations ?: [0])
            ->whereIn('date', array_values(array_unique($newest)))
            ->get(['character_id', 'date', 'ticks']);

        $last = [];
        foreach ($rows as $row) {
            $id = (int) $row->character_id;
            $day = substr((string) $row->date, 0, 10);
            if ($day !== substr((string) $newest[$id], 0, 10)) {
                continue;
            }

            $ticks = json_decode($row->ticks, true);
            $time = gmdate('Y-m-d H:i:s', strtotime($day . ' 00:00:00 UTC') + (int) end($ticks)[0]);
            if ($time > ($last[$id] ?? '')) {
                $last[$id] = $time;
            }
        }

        return $last;
    }

    private static function read(Builder $query, Carbon $from, Carbon $to): LazyCollection
    {
        [$fromSecond, $toSecond] = [self::second($from), self::second($to)];
        $kinds = array_flip(self::KINDS);

        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->select(['date', 'character_id', 'corporation_id', 'ticks']);

        return LazyCollection::make(function () use ($query, $fromSecond, $toSecond, $kinds) {
            $day = null;
            $dayStart = 0;
            $ticks = [];

            foreach ($query->cursor() as $row) {
                $date = substr((string) $row->date, 0, 10);
                if ($date !== $day) {
                    // A day's ticks are held until the next day starts, so
                    // ticks can come oldest first without holding the year.
                    foreach (self::oldestFirst($ticks) as $tick) {
                        yield $tick;
                    }
                    $ticks = [];
                    $day = $date;
                    $dayStart = strtotime($date . ' 00:00:00 UTC');
                }

                foreach (json_decode($row->ticks, true) as [$offset, $system, $net, $rats, $group, $kind]) {
                    $second = $dayStart + $offset;
                    if ($second < $fromSecond || $second > $toSecond) {
                        continue;
                    }

                    $ticks[] = (object) [
                        'character_id' => (int) $row->character_id,
                        'corporation_id' => (int) $row->corporation_id,
                        'solar_system_id' => $system,
                        'net_amount' => $net,
                        'npc_kills' => $rats,
                        'kill_group' => $group,
                        'kind' => $kinds[$kind] ?? $kind,
                        'ts' => $second,
                    ];
                }
            }

            foreach (self::oldestFirst($ticks) as $tick) {
                yield $tick;
            }
        });
    }

    /**
     * Ticks of the same second are common in fleets, and time is credited to
     * whichever comes first, so their order has to stay what Ticks::stream()
     * gives from the tick table: by character, and within a character as
     * written. Another order would move a player's time between systems.
     */
    private static function oldestFirst(array $ticks): array
    {
        usort($ticks, fn ($a, $b) => [$a->ts, $a->character_id] <=> [$b->ts, $b->character_id]);

        return $ticks;
    }

    /** A time as the seconds the tick table's dates stand for, which are EVE time. */
    private static function second(Carbon $time): int
    {
        return strtotime($time->format('Y-m-d H:i:s') . ' UTC');
    }
}
