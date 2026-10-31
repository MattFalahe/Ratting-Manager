<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Npc\NpcRegistry;
use RattingManager\Services\Universe\SolarSystems;

/**
 * Data for every chart, shaped the way the pages hand it to Chart.js.
 */
class ChartData
{
    private Players $players;
    private SolarSystems $systems;

    public function __construct(Players $players, SolarSystems $systems)
    {
        $this->players = $players;
        $this->systems = $systems;
    }

    /**
     * A query's rows, kept with the other figures until the next import
     * changes the data. Only the rows are kept: names and system details
     * are put on after, so a character still being looked up is named as
     * soon as the lookup has them.
     *
     * @param callable(): \Illuminate\Support\Collection $query
     */
    private function rows(string $name, Scope $scope, string $extra, callable $query): \Illuminate\Support\Collection
    {
        return collect(FigureCache::remember(
            'chart:' . $name . ':' . $scope->cacheKey() . ':' . $extra,
            fn () => $query()->all()
        ));
    }

    /** Income per month for the last twelve months, split by what was read and what was worked out. */
    public function monthly(Scope $scope, bool $withCorpTax = false): array
    {
        $start = now()->subMonthsNoOverflow(11)->startOfMonth();

        $rows = $this->rows('monthly', $scope, $start->format('Y-m'), fn () => Summaries::months($scope, $start->format('Y-m'))
            ->groupBy('month')
            ->selectRaw('month, SUM(bounty_net) as bounty, SUM(bounty_estimated) as bounty_estimated, SUM(ess_net) as ess, SUM(ess_estimated) as ess_estimated, SUM(bounty_corp_tax + ess_corp_tax) as corp_tax')
            ->get()
            ->keyBy('month'));

        $data = ['labels' => [], 'bounty' => [], 'bounty_estimated' => [], 'ess' => [], 'ess_estimated' => [], 'corp_tax' => []];
        for ($m = $start->copy(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $r = $rows->get($m->format('Y-m'));
            $data['labels'][] = $m->format('M Y');
            $data['bounty'][] = round((float) ($r->bounty ?? 0) - (float) ($r->bounty_estimated ?? 0), 2);
            $data['bounty_estimated'][] = round((float) ($r->bounty_estimated ?? 0), 2);
            $data['ess'][] = round((float) ($r->ess ?? 0) - (float) ($r->ess_estimated ?? 0), 2);
            $data['ess_estimated'][] = round((float) ($r->ess_estimated ?? 0), 2);
            $data['corp_tax'][] = round((float) ($r->corp_tax ?? 0), 2);
        }

        if (!$withCorpTax) {
            unset($data['corp_tax']);
        }

        return $data;
    }

    /** Income per day across the period, with a trailing seven-day average. */
    public function daily(Scope $scope, Period $period): array
    {
        $rows = $this->rows('daily', $scope, FigureCache::range($period->start, $period->end), fn () => $scope->apply(DB::table('ratting_manager_daily_summaries'))
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('date')
            ->selectRaw('date, SUM(bounty_net + ess_net) as income')
            ->pluck('income', 'date'));

        $data = ['labels' => [], 'income' => [], 'average' => []];
        $window = [];
        for ($d = $period->start->copy()->startOfDay(); $d->lte($period->end); $d->addDay()) {
            $value = round((float) ($rows[$d->toDateString()] ?? 0), 2);
            $window[] = $value;
            if (count($window) > 7) {
                array_shift($window);
            }
            $data['labels'][] = $d->format('M j');
            $data['income'][] = $value;
            $data['average'][] = round(array_sum($window) / count($window), 2);
        }

        return $data;
    }

    /** Where the ISK was made, best systems first. */
    public function systems(Scope $scope, Period $period, int $limit = 15): array
    {
        $rows = $this->rows('systems', $scope, FigureCache::range($period->start, $period->end) . ':' . $limit, fn () => Summaries::between($scope, $period->start, $period->end)
            ->groupBy('solar_system_id')
            ->selectRaw('solar_system_id, SUM(bounty_net + ess_net) as income, SUM(bounty_corp_tax + ess_corp_tax) as corp_tax, SUM(ticks) as ticks, COUNT(DISTINCT character_id) as characters')
            ->orderByDesc('income')
            ->limit($limit)
            ->get());

        $details = $this->systems->details($rows->pluck('solar_system_id')->map(fn ($id) => (int) $id)->filter()->all());

        return $rows->map(function ($r) use ($details) {
            $id = (int) $r->solar_system_id;

            return [
                'system_id' => $id,
                'name' => $id ? ($details[$id]['name'] ?? 'System #' . $id) : trans('ratting-manager::common.system_not_given'),
                'security' => $id ? ($details[$id]['security'] ?? null) : null,
                'income' => round((float) $r->income, 2),
                'ratted' => round((float) $r->income + (float) ($r->corp_tax ?? 0), 2),
                'ticks' => (int) $r->ticks,
                'characters' => (int) $r->characters,
            ];
        })->all();
    }

    /**
     * How much of New Eden the scope ratted in.
     *
     * @return array{systems: int, constellations: int, regions: int}
     */
    public function spaceCounts(Scope $scope, Period $period): array
    {
        return FigureCache::remember(
            'chart:space:' . $scope->cacheKey() . ':' . FigureCache::range($period->start, $period->end),
            function () use ($scope, $period) {
                $systems = Summaries::between($scope, $period->start, $period->end)
                    ->distinct()
                    ->pluck('solar_system_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all();

                $constellations = [];
                $regions = [];
                foreach (array_chunk($systems, 1000) as $chunk) {
                    foreach (DB::table('solar_systems')->whereIn('system_id', $chunk)->get(['constellation_id', 'region_id']) as $row) {
                        $constellations[$row->constellation_id] = true;
                        $regions[$row->region_id] = true;
                    }
                }

                return ['systems' => count($systems), 'constellations' => count($constellations), 'regions' => count($regions)];
            }
        );
    }

    /**
     * Rats killed by faction and by size. A fleet's shared payout counts once.
     *
     * @return array{factions: array, sizes: array, ranks: array}
     */
    public function kills(Scope $scope, Period $period): array
    {
        $factions = [];
        $sizes = array_fill_keys(['small', 'medium', 'large', 'capital', 'unknown'], 0);
        $ranks = array_fill_keys(NpcRegistry::RANKS, 0);
        foreach (Ticks::kills($scope, $period->start, $period->end) as $r) {
            $factions[$r['faction']] = ($factions[$r['faction']] ?? 0) + $r['quantity'];
            $sizes[$r['size']] = ($sizes[$r['size']] ?? 0) + $r['quantity'];
            $ranks[$r['rank']] = ($ranks[$r['rank']] ?? 0) + $r['quantity'];
        }
        arsort($factions);

        return [
            'factions' => [
                'keys' => array_keys($factions),
                'labels' => array_map(fn ($f) => NpcRegistry::FACTIONS[$f] ?? ucfirst($f), array_keys($factions)),
                'values' => array_values($factions),
            ],
            'sizes' => [
                'labels' => array_map(fn ($s) => trans('ratting-manager::common.size_' . $s), array_keys($sizes)),
                'values' => array_values($sizes),
            ],
            'ranks' => $ranks,
        ];
    }

    /** Income per character, for a player with alts. */
    public function byCharacter(Scope $scope, Period $period): array
    {
        $rows = $this->rows('by-character', $scope, FigureCache::range($period->start, $period->end), fn () => Summaries::between($scope, $period->start, $period->end)
            ->groupBy('character_id')
            ->selectRaw('character_id, SUM(bounty_net + ess_net) as income')
            ->orderByDesc('income')
            // Fleet members often earn the same, so equal incomes need an order of their own.
            ->orderBy('character_id')
            ->get());

        $this->players->load($rows->pluck('character_id')->map(fn ($id) => (int) $id)->all());

        return [
            'labels' => $rows->map(fn ($r) => $this->players->name((int) $r->character_id))->all(),
            'values' => $rows->map(fn ($r) => round((float) $r->income, 2))->all(),
        ];
    }

    public function sessions(Scope $scope, Period $period, int $limit = 20): array
    {
        $rows = $this->rows('sessions', $scope, FigureCache::range($period->start, $period->end), fn () => $scope->apply(DB::table('ratting_manager_sessions'))
            ->whereBetween('started_at', [$period->start->copy()->subMinutes(ActiveTime::TICK_MINUTES), $period->end])
            ->orderByDesc('started_at')
            ->get());

        $this->players->load($rows->pluck('character_id')->map(fn ($id) => (int) $id)->all());
        $details = $this->systems->details($rows->pluck('primary_system_id')->map(fn ($id) => (int) $id)->all());

        $list = $rows->take($limit)->map(fn ($s) => [
            'character' => $this->players->name((int) $s->character_id),
            'started_at' => $s->started_at,
            'ended_at' => $s->ended_at,
            'minutes' => (int) $s->minutes,
            'system' => $details[(int) $s->primary_system_id]['name'] ?? '-',
            'system_count' => (int) $s->system_count,
            'ticks' => (int) $s->tick_count,
            'rats' => (int) $s->npc_kills,
            'net' => (float) $s->net_amount,
            'estimated' => (float) $s->estimated_amount > 0,
            'isk_per_hour' => (float) $s->isk_per_hour,
        ])->all();

        $points = $rows->map(fn ($s) => [
            'x' => Carbon::parse($s->started_at)->getTimestamp() * 1000,
            'y' => round((float) $s->isk_per_hour, 2),
            'minutes' => (int) $s->minutes,
        ])->values()->all();

        return ['list' => $list, 'points' => $points, 'total' => $rows->count()];
    }

    /** Best tick, session, day and month, and the longest session, over the last year. */
    public function records(Scope $scope): array
    {
        return FigureCache::remember('chart:records:' . $scope->cacheKey(), fn () => $this->computeRecords($scope));
    }

    private function computeRecords(Scope $scope): array
    {
        $tick = null;
        foreach (Ticks::stream($scope, RawTicks::oldestDay(), now(), ['net_amount'], true) as $t) {
            if ($tick === null || (float) $t->net_amount > (float) $tick->net_amount) {
                $tick = $t;
            }
        }
        $session = $scope->apply(DB::table('ratting_manager_sessions'))->orderByDesc('net_amount')->first(['net_amount', 'started_at', 'minutes']);
        $longest = $scope->apply(DB::table('ratting_manager_sessions'))->orderByDesc('minutes')->first(['minutes', 'started_at', 'net_amount']);
        $day = $scope->apply(DB::table('ratting_manager_daily_summaries'))
            ->groupBy('date')->selectRaw('date, SUM(bounty_net + ess_net) as income')->orderByDesc('income')->first();
        // The month rows reach further back than the rest, so they are held to the same year.
        $month = Summaries::months($scope, now()->subMonthsNoOverflow(11)->format('Y-m'))
            ->groupBy('month')
            ->selectRaw('month, SUM(bounty_net + ess_net) as income')->orderByDesc('income')->first();

        return [
            'best_tick' => $tick ? ['value' => (float) $tick->net_amount, 'when' => gmdate('Y-m-d H:i:s', (int) $tick->ts)] : null,
            'best_session' => $session ? ['value' => (float) $session->net_amount, 'when' => $session->started_at, 'minutes' => (int) $session->minutes] : null,
            'longest_session' => $longest ? ['minutes' => (int) $longest->minutes, 'when' => $longest->started_at, 'value' => (float) $longest->net_amount] : null,
            'best_day' => $day ? ['value' => (float) $day->income, 'when' => $day->date] : null,
            'best_month' => $month ? ['value' => (float) $month->income, 'when' => Carbon::parse($month->month . '-01')->format('F Y')] : null,
        ];
    }

    /** How many special spawns the scope has in all. */
    public function spawnCount(Scope $scope): int
    {
        return (int) FigureCache::remember(
            'chart:spawn-count:' . $scope->cacheKey(),
            fn () => $scope->apply(DB::table('ratting_manager_special_spawns as s'), 's')->count()
        );
    }

    public function spawns(Scope $scope, int $limit = 25): array
    {
        $rows = $this->rows('spawns', $scope, (string) $limit, fn () => $scope->apply(DB::table('ratting_manager_special_spawns as s'), 's')
            ->leftJoin('ratting_manager_npc_types as n', 'n.type_id', '=', 's.npc_type_id')
            ->orderByDesc('s.ticked_at')
            ->limit($limit)
            ->get(['s.ticked_at', 's.character_id', 's.solar_system_id', 's.quantity', 'n.type_id', 'n.name', 'n.faction', 'n.rank', 'n.size']));

        $this->players->load($rows->pluck('character_id')->map(fn ($id) => (int) $id)->all());
        $details = $this->systems->details($rows->pluck('solar_system_id')->map(fn ($id) => (int) $id)->all());

        return $rows->map(fn ($r) => [
            'when' => $r->ticked_at,
            'character' => $this->players->name((int) $r->character_id),
            'system' => $details[(int) $r->solar_system_id]['name'] ?? '-',
            'npc' => $r->name ?? ('Type ' . $r->type_id),
            'type_id' => (int) $r->type_id,
            'faction' => $r->faction ?? 'other',
            'rank' => $r->rank ?? 'regular',
            'size' => $r->size ?? 'unknown',
            'quantity' => (int) $r->quantity,
        ])->all();
    }

    /**
     * Players and characters ratting each month, and how the players moved:
     * new, back after a gap, still going, or stopped since last month.
     */
    public function ratterMovement(Scope $scope): array
    {
        $rows = $this->rows('movement', $scope, '', fn () => Summaries::months($scope)
            ->where('ticks', '>', 0)
            ->groupBy('character_id', 'month')
            ->selectRaw('character_id, month')
            ->get());

        $this->players->load($rows->pluck('character_id')->map(fn ($id) => (int) $id)->all());

        $playersByMonth = [];
        $charactersByMonth = [];
        foreach ($rows as $r) {
            $playersByMonth[$r->month][$this->players->mainOf((int) $r->character_id)] = true;
            $charactersByMonth[$r->month][(int) $r->character_id] = true;
        }
        ksort($playersByMonth);

        $start = now()->subMonthsNoOverflow(11)->startOfMonth();
        $everBefore = [];
        foreach ($playersByMonth as $month => $set) {
            if ($month < $start->format('Y-m')) {
                $everBefore += $set;
            }
        }

        $data = ['labels' => [], 'players' => [], 'characters' => [], 'new' => [], 'returning' => [], 'continuing' => [], 'lapsed' => []];
        $previous = $playersByMonth[$start->copy()->subMonthNoOverflow()->format('Y-m')] ?? [];
        for ($m = $start->copy(); $m->lte(now()); $m->addMonthNoOverflow()) {
            $key = $m->format('Y-m');
            $current = $playersByMonth[$key] ?? [];

            $new = $returning = $continuing = 0;
            foreach (array_keys($current) as $main) {
                if (isset($previous[$main])) {
                    $continuing++;
                } elseif (isset($everBefore[$main])) {
                    $returning++;
                } else {
                    $new++;
                }
            }

            $data['labels'][] = $m->format('M Y');
            $data['players'][] = count($current);
            $data['characters'][] = count($charactersByMonth[$key] ?? []);
            $data['new'][] = $new;
            $data['returning'][] = $returning;
            $data['continuing'][] = $continuing;
            $data['lapsed'][] = count(array_diff_key($previous, $current));

            $everBefore += $current;
            $previous = $current;
        }

        return $data;
    }

    /** How the period's income is shared out: the top player, the next four, the next five, and everyone else. */
    public function concentration(array $leaderboardRows): array
    {
        $incomes = array_map(fn ($r) => $r['income'], $leaderboardRows);
        rsort($incomes);

        return [
            'labels' => [
                trans('ratting-manager::dashboard.concentration_top_1'),
                trans('ratting-manager::dashboard.concentration_2_5'),
                trans('ratting-manager::dashboard.concentration_6_10'),
                trans('ratting-manager::dashboard.concentration_rest'),
            ],
            'values' => [
                round(array_sum(array_slice($incomes, 0, 1)), 2),
                round(array_sum(array_slice($incomes, 1, 4)), 2),
                round(array_sum(array_slice($incomes, 5, 5)), 2),
                round(array_sum(array_slice($incomes, 10)), 2),
            ],
        ];
    }
}
