<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Reading ticks for the figures that cannot come from the daily summaries.
 *
 * A year of a big corporation is hundreds of thousands of ticks, so they are
 * streamed rather than held, and the time comes back as unix seconds in `ts`
 * so the loops work on integers instead of parsing a date per row. Ticks are
 * stored in EVE time, which is UTC, so `ts` is what strtotime() would give.
 *
 * They are read from the character days once those are filled, which carry
 * every column asked for here, and from the tick table until then.
 */
final class Ticks
{
    /**
     * @param string[] $columns tick columns besides the time
     */
    public static function stream(Scope $scope, Carbon $from, Carbon $to, array $columns, bool $oldestFirst = false): LazyCollection
    {
        if (Summaries::ready()) {
            return CharacterDays::ticks($scope, $from, $to);
        }

        $query = $scope->apply(DB::table('ratting_manager_ticks'))
            ->whereBetween('ticked_at', [$from, $to])
            ->select($columns)
            ->selectRaw("TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', ticked_at) as ts");

        if ($oldestFirst) {
            $query->orderBy('ticked_at')->orderBy('character_id');
        }

        return $query->cursor();
    }

    /**
     * The characters with ratting in the scope between two times, from the
     * daily summaries, so their players can be loaded in one go before the
     * ticks are streamed.
     *
     * @return int[]
     */
    public static function characters(Scope $scope, Carbon $from, Carbon $to): array
    {
        return Summaries::between($scope, $from, $to)
            ->distinct()
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * How many of each character's ticks since a time came from which
     * journal, in these corporations.
     *
     * @param int[] $characters
     * @param int[] $corporations
     * @return array<int, array<string, int>> character_id => kind => ticks
     */
    public static function kindsOf(array $characters, Carbon $since, array $corporations): array
    {
        $counts = [];

        if (Summaries::ready()) {
            foreach (CharacterDays::ticks(new Scope($corporations, $characters), $since, Carbon::now()) as $t) {
                $counts[$t->character_id][$t->kind] = ($counts[$t->character_id][$t->kind] ?? 0) + 1;
            }

            return $counts;
        }

        $rows = DB::table('ratting_manager_ticks')
            ->whereIn('character_id', $characters ?: [0])
            ->whereIn('corporation_id', $corporations ?: [0])
            ->where('ticked_at', '>=', $since)
            ->groupBy('character_id', 'kind')
            ->selectRaw('character_id, kind, COUNT(*) as n')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->character_id][$row->kind] = (int) $row->n;
        }

        return $counts;
    }

    /**
     * When each character's last tick for these corporations paid, in EVE time.
     *
     * @param int[] $characters
     * @param int[] $corporations
     * @return array<int, string> character_id => Y-m-d H:i:s
     */
    public static function lastTicks(array $characters, array $corporations): array
    {
        $last = [];

        foreach (array_chunk($characters, 1000) as $chunk) {
            $last += Summaries::ready()
                ? CharacterDays::lastTicks($chunk, $corporations)
                : DB::table('ratting_manager_ticks')
                    ->whereIn('character_id', $chunk)
                    ->whereIn('corporation_id', $corporations ?: [0])
                    ->groupBy('character_id')
                    ->selectRaw('character_id, MAX(ticked_at) as last_tick')
                    ->pluck('last_tick', 'character_id')
                    ->all();
        }

        return $last;
    }

    /**
     * Rats killed per NPC type, a fleet's shared payout counted once, with
     * what is known of each type.
     *
     * @return array<int, array{npc_type_id: int, quantity: int, name: ?string, faction: string, size: string, rank: string}>
     *         most killed first
     */
    public static function kills(Scope $scope, Carbon $from, Carbon $to): array
    {
        $key = 'kills:' . $scope->cacheKey() . ':' . FigureCache::range($from, $to);

        return FigureCache::remember($key, function () use ($scope, $from, $to) {
            $quantities = self::killsByType($scope, $from, $to);
            $types = DB::table('ratting_manager_npc_types')
                ->whereIn('type_id', array_keys($quantities) ?: [0])
                ->get(['type_id', 'name', 'faction', 'size', 'rank'])
                ->keyBy('type_id');

            $rows = [];
            foreach ($quantities as $typeId => $quantity) {
                $type = $types->get($typeId);
                $rows[] = [
                    'npc_type_id' => (int) $typeId,
                    'quantity' => (int) $quantity,
                    'name' => $type->name ?? null,
                    'faction' => $type->faction ?? 'other',
                    'size' => $type->size ?? 'unknown',
                    'rank' => $type->rank ?? 'regular',
                ];
            }
            // Types killed as often as each other need an order of their own, or
            // the list shuffles with where the numbers were read from.
            usort($rows, fn ($a, $b) => [$b['quantity'], $a['npc_type_id']] <=> [$a['quantity'], $b['npc_type_id']]);

            return $rows;
        });
    }

    /**
     * The numbers behind kills().
     *
     * One whole corporation reads its daily kill totals, for the days they are
     * still kept. Anything else reads each character's kill lists: the daily
     * totals are not kept per character, and a fleet with members in two
     * corporations is in both corporations' totals. Every tick in a kill group
     * paid for the same kills, so one tick per group stands for the group.
     *
     * @return array<int, int> npc_type_id => quantity
     */
    private static function killsByType(Scope $scope, Carbon $from, Carbon $to): array
    {
        if ($scope->characters === null && count($scope->corporations) === 1) {
            $kept = RawTicks::keptFrom();

            if ($kept === null || $from->gte($kept)) {
                return self::dailyKills($scope->corporations[0], $from, $to);
            }

            // The daily totals go with the raw ticks, and the kill lists stay
            // for the year, so the days before the line are read from those.
            $quantities = CharacterDays::killsByType($scope, $from, $to->lt($kept) ? $to : $kept->copy()->subSecond());
            if ($to->gte($kept)) {
                foreach (self::dailyKills($scope->corporations[0], $kept, $to) as $type => $quantity) {
                    $quantities[$type] = ($quantities[$type] ?? 0) + (int) $quantity;
                }
            }

            return $quantities;
        }

        if (Summaries::ready()) {
            return CharacterDays::killsByType($scope, $from, $to);
        }

        $groups = $scope->apply(DB::table('ratting_manager_ticks'))
            ->whereBetween('ticked_at', [$from, $to])
            ->whereNotNull('kill_group')
            ->groupBy('kill_group')
            ->selectRaw('MIN(id) as id');

        return DB::table('ratting_manager_tick_kills as k')
            ->joinSub($groups, 'g', 'g.id', '=', 'k.tick_id')
            ->groupBy('k.npc_type_id')
            ->selectRaw('k.npc_type_id, SUM(k.quantity) as quantity')
            ->pluck('quantity', 'npc_type_id')
            ->all();
    }

    /** @return array<int, int|string> npc_type_id => quantity */
    private static function dailyKills(int $corporation, Carbon $from, Carbon $to): array
    {
        return DB::table('ratting_manager_daily_kills')
            ->where('corporation_id', $corporation)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('npc_type_id')
            ->selectRaw('npc_type_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'npc_type_id')
            ->all();
    }
}
