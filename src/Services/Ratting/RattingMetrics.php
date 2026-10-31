<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;

/**
 * Figures per player and in total, for the dashboards and the leaderboard.
 *
 * ISK comes from the summaries. Time, rats and coverage come from the ticks
 * themselves, because they cannot be added up across characters: a
 * player's alts ratting together are one stretch of time, and a fleet's
 * shared payout is one set of rats.
 */
class RattingMetrics
{
    private Players $players;
    private Settings $settings;

    public function __construct(Players $players, Settings $settings)
    {
        $this->players = $players;
        $this->settings = $settings;
    }

    /**
     * @return array{players: array<int, array>, totals: array}
     */
    public function collect(Scope $scope, Carbon $from, Carbon $to): array
    {
        $key = 'collect:' . $scope->cacheKey() . ':' . FigureCache::range($from, $to);

        $collected = FigureCache::remember($key, fn () => $this->compute($scope, $from, $to));

        // Names go on after the cache, so a character still being looked up
        // when the figures were cached is named as soon as the lookup has them.
        $this->players->load(array_keys($collected['players']));
        foreach ($collected['players'] as $main => &$player) {
            $player['name'] = $this->players->name((int) $main);
            $player['ratted'] = self::ratted($player);
        }
        unset($player);
        $collected['totals']['ratted'] = self::ratted($collected['totals']);

        return $collected;
    }

    /**
     * ISK ratted: what the rats paid before tax, so what the pilots received
     * plus the corporation's cut. A payout too small for the corporation to
     * take a cut of was received whole, so it counts as it is.
     *
     * Worked out here and not kept, so it needs nothing rebuilt, and it stands
     * after a drop, since it comes from the summaries.
     */
    private static function ratted(array $figures): float
    {
        return round($figures['income'] + $figures['corp_tax'], 2);
    }

    /**
     * Every player in the scope with their period figures, best first by
     * income, each with ISK per hour against the median of players who
     * ratted at least an hour.
     *
     * @return array{rows: array<int, array>, median_isk_per_hour: float, totals: array}
     */
    public function leaderboard(Scope $scope, Period $period): array
    {
        $current = $this->collect($scope, $period->start, $period->end);
        // A period before the data starts, such as the year before "Last 12
        // months" when the first import brought in one year, would show every
        // player as a jump from almost nothing, so it is not compared at all.
        $previous = $this->covers($scope, $period->previousStart, $period->previousEnd)
            ? $this->income($scope, $period->previousStart, $period->previousEnd)
            : [];

        $rows = array_values($current['players']);
        $rates = array_values(array_filter(array_map(
            fn ($r) => $r['minutes'] >= 60 ? $r['isk_per_hour'] : null,
            $rows
        ), fn ($v) => $v !== null));
        $median = $this->median($rates);

        foreach ($rows as &$row) {
            $row['efficiency'] = $median > 0 && $row['minutes'] >= 60 ? round($row['isk_per_hour'] / $median * 100) : null;
            $before = $previous[$row['main_id']] ?? 0.0;
            $row['previous_income'] = $before;
            $row['change'] = $before > 0 ? round(($row['income'] - $before) / $before * 100) : null;
        }
        unset($row);

        usort($rows, fn ($a, $b) => $b['income'] <=> $a['income']);

        return ['rows' => $rows, 'median_isk_per_hour' => $median, 'totals' => $current['totals']];
    }

    /**
     * Rank by hours, which is all a member sees of other players.
     *
     * @return array{rows: array<int, array>, own_rank: ?int}
     */
    public function hoursBoard(Scope $scope, Period $period, int $ownMain): array
    {
        // A player with only an ESS payout in the period has income but no
        // ratting time in it, so they have no place on a board of hours.
        $rows = array_values(array_filter(
            $this->collect($scope, $period->start, $period->end)['players'],
            fn ($row) => $row['minutes'] > 0
        ));
        usort($rows, fn ($a, $b) => [$b['minutes'], $b['active_days']] <=> [$a['minutes'], $a['active_days']]);

        $ownRank = null;
        $board = [];
        foreach ($rows as $i => $row) {
            if ($row['main_id'] === $ownMain) {
                $ownRank = $i + 1;
            }
            $board[] = [
                'rank' => $i + 1,
                'main_id' => $row['main_id'],
                'name' => $row['name'],
                'minutes' => $row['minutes'],
                'active_days' => $row['active_days'],
            ];
        }

        return ['rows' => $board, 'own_rank' => $ownRank, 'players' => count($board)];
    }

    /**
     * Players who ratted regularly and have stopped.
     *
     * @param int[] $corporations
     */
    public function goneQuiet(array $corporations): array
    {
        $regular = now()->subDays((int) $this->settings->get('data.regular_ratter_days'));
        $quiet = now()->subDays((int) $this->settings->get('data.gone_quiet_days'));

        // The rows are kept with the other figures; who counts as gone
        // quiet is still decided below against the time now.
        $last = collect(FigureCache::remember(
            'gone-quiet:' . md5(json_encode($corporations)) . ':' . $regular->toDateString(),
            function () use ($corporations, $regular) {
                $rows = [];
                foreach (Ticks::stream(new Scope($corporations), $regular, now(), ['character_id'], true) as $t) {
                    $id = (int) $t->character_id;
                    $rows[$id] ??= (object) ['character_id' => $id, 'last_tick' => 0, 'ticks' => 0];
                    $rows[$id]->last_tick = (int) $t->ts;
                    $rows[$id]->ticks++;
                }
                ksort($rows);

                return array_values(array_map(function ($row) {
                    $row->last_tick = gmdate('Y-m-d H:i:s', $row->last_tick);

                    return $row;
                }, $rows));
            }
        ));

        $this->players->load($last->pluck('character_id')->map(fn ($id) => (int) $id)->all());

        $byPlayer = [];
        foreach ($last as $row) {
            $main = $this->players->mainOf((int) $row->character_id);
            $byPlayer[$main] ??= ['main_id' => $main, 'name' => $this->players->name($main), 'last_tick' => null, 'ticks' => 0];
            $byPlayer[$main]['ticks'] += (int) $row->ticks;
            if ($byPlayer[$main]['last_tick'] === null || $row->last_tick > $byPlayer[$main]['last_tick']) {
                $byPlayer[$main]['last_tick'] = $row->last_tick;
            }
        }

        $rows = array_values(array_filter($byPlayer, fn ($p) => Carbon::parse($p['last_tick'])->lt($quiet)));
        usort($rows, fn ($a, $b) => strcmp($b['last_tick'], $a['last_tick']));

        // Someone who has left is quiet for a reason, so the list says so.
        return $this->players->markStanding($rows, $corporations);
    }

    /**
     * Ratters the corporation journal sees who have no wallet token on SeAT,
     * so everything shown for them is worked out from the corporation's cut.
     *
     * @param int[] $corporations
     */
    public function untracked(array $corporations, int $days = 90): array
    {
        // The rows are kept with the other figures; whether each ratter has
        // a wallet token yet is still checked below on every read.
        $since = now()->subDays($days);
        $rows = collect(FigureCache::remember(
            'untracked:' . md5(json_encode($corporations)) . ':' . $days . ':' . $since->toDateString(),
            function () use ($corporations, $since) {
                $rows = [];
                foreach (Ticks::stream(new Scope($corporations), $since, now(), ['character_id', 'kind', 'net_amount'], true) as $t) {
                    if ($t->kind !== 'corporation') {
                        continue;
                    }

                    $id = (int) $t->character_id;
                    $rows[$id] ??= (object) ['character_id' => $id, 'ticks' => 0, 'net' => 0.0, 'last_tick' => 0];
                    $rows[$id]->ticks++;
                    $rows[$id]->net += (float) $t->net_amount;
                    $rows[$id]->last_tick = (int) $t->ts;
                }
                ksort($rows);

                return array_values(array_map(function ($row) {
                    $row->last_tick = gmdate('Y-m-d H:i:s', $row->last_tick);

                    return $row;
                }, $rows));
            }
        ));

        $ids = $rows->pluck('character_id')->map(fn ($id) => (int) $id)->all();
        $this->players->load($ids);
        // Someone who had a token and lost it is on the list of lost tokens,
        // which says how long there is to fix it.
        $lost = app(WalletGaps::class)->forCharacters($ids);

        $list = [];
        foreach ($rows as $row) {
            $id = (int) $row->character_id;
            if ($this->players->hasWalletToken($id) || isset($lost[$id])) {
                // A token with the wallet scope that has not brought rows in
                // yet; the coverage badge covers that case.
                continue;
            }
            $list[] = [
                'character_id' => $id,
                'name' => $this->players->name($id),
                'corporation' => $this->players->currentCorporationName($id),
                'on_seat' => $this->players->isKnownToSeat($id),
                'ticks' => (int) $row->ticks,
                'net' => (float) $row->net,
                'last_tick' => $row->last_tick,
            ];
        }

        usort($list, fn ($a, $b) => $b['net'] <=> $a['net']);

        return $list;
    }

    /** @return array<int, float> main_id => income, ISK only, for the change column */
    private function income(Scope $scope, Carbon $from, Carbon $to): array
    {
        $rows = Summaries::between($scope, $from, $to)
            ->groupBy('character_id')
            ->selectRaw('character_id, SUM(bounty_net + ess_net) as income')
            ->get();

        $this->players->load($rows->pluck('character_id')->map(fn ($id) => (int) $id)->all());

        $income = [];
        foreach ($rows as $row) {
            $main = $this->players->mainOf((int) $row->character_id);
            $income[$main] = ($income[$main] ?? 0) + (float) $row->income;
        }

        return $income;
    }

    /**
     * Whether the scope's data reaches back over a stretch, so a comparison
     * with it is like with like. Daily rows stop after a year, and whole months
     * can be read for a year more. Only a month with another before it is sure
     * to be whole: the first month on file may have started part way through.
     */
    public function covers(Scope $scope, Carbon $from, Carbon $to): bool
    {
        [$firstDay, $firstMonth] = FigureCache::remember(
            'first-rows:' . $scope->cacheKey(),
            fn () => [
                $scope->apply(DB::table(Summaries::DAILY))->min('date'),
                Summaries::ready() ? $scope->apply(DB::table(Summaries::MONTHLY))->min('month') : null,
            ]
        );

        if ($firstDay !== null && substr((string) $firstDay, 0, 10) <= $from->toDateString()) {
            return true;
        }

        return $firstMonth !== null
            && $firstMonth < $from->format('Y-m')
            && $from->day === 1
            && $to->toDateString() === $to->copy()->endOfMonth()->toDateString();
    }

    private function compute(Scope $scope, Carbon $from, Carbon $to): array
    {
        $money = Summaries::between($scope, $from, $to)
            ->groupBy('character_id')
            ->selectRaw('character_id, SUM(bounty_net) as bounty, SUM(bounty_estimated) as bounty_estimated, SUM(ess_net) as ess, SUM(ess_estimated) as ess_estimated, SUM(bounty_corp_tax + ess_corp_tax) as corp_tax')
            ->get()
            ->keyBy('character_id');

        $this->players->load(array_merge(
            $money->keys()->map(fn ($id) => (int) $id)->all(),
            Ticks::characters($scope, $from, $to)
        ));

        $players = [];
        $blank = fn (int $main) => [
            'main_id' => $main,
            'name' => '',
            'characters' => [],
            'income' => 0.0, 'bounty' => 0.0, 'ess' => 0.0, 'estimated' => 0.0, 'corp_tax' => 0.0,
            'ticks' => 0, 'minutes' => 0, 'isk_per_hour' => 0.0, 'rats' => 0, 'active_days' => 0,
            'coverage' => null, 'wallet_token' => false, 'last_tick' => null,
        ];

        foreach ($money as $characterId => $m) {
            $main = $this->players->mainOf((int) $characterId);
            $players[$main] ??= $blank($main);
            $p = &$players[$main];
            $p['characters'][(int) $characterId] = true;
            $p['bounty'] += (float) $m->bounty;
            $p['ess'] += (float) $m->ess;
            $p['estimated'] += (float) $m->bounty_estimated + (float) $m->ess_estimated;
            $p['corp_tax'] += (float) $m->corp_tax;
            unset($p);
        }

        // Ticks come oldest first, so each player's time is counted as they
        // arrive (ActiveTimeCounter) instead of keeping every timestamp. A
        // kill group is one second, one system and one kill list, so it never
        // spans two seconds: only the groups of the current second need
        // remembering to count each group once.
        $counters = [];
        $rats = [];
        $allRats = 0;
        $groupsThisSecond = [];
        $playerGroupsThisSecond = [];
        $second = null;
        $days = [];
        $seenByCorp = [];
        $paired = [];
        $mains = [];
        $last = [];
        foreach (Ticks::stream($scope, $from, $to, ['character_id', 'kill_group', 'npc_kills', 'kind'], true) as $t) {
            $main = $mains[(int) $t->character_id] ??= $this->players->mainOf((int) $t->character_id);
            $time = (int) $t->ts;
            $players[$main] ??= $blank($main);
            $players[$main]['characters'][(int) $t->character_id] = true;
            $players[$main]['ticks']++;
            if ($time > ($last[$main] ?? 0)) {
                $last[$main] = $time;
            }

            ($counters[$main] ??= new ActiveTimeCounter())->add($time);
            $days[$main][intdiv($time, 86400)] = true;

            if ($time !== $second) {
                $second = $time;
                $groupsThisSecond = [];
                $playerGroupsThisSecond = [];
            }
            if ($t->kill_group !== null) {
                if (!isset($playerGroupsThisSecond[$main][$t->kill_group])) {
                    $playerGroupsThisSecond[$main][$t->kill_group] = true;
                    $rats[$main] = ($rats[$main] ?? 0) + (int) $t->npc_kills;
                }
                if (!isset($groupsThisSecond[$t->kill_group])) {
                    $groupsThisSecond[$t->kill_group] = true;
                    $allRats += (int) $t->npc_kills;
                }
            }

            if ($t->kind !== 'character') {
                $seenByCorp[$main] = ($seenByCorp[$main] ?? 0) + 1;
                if ($t->kind === 'paired') {
                    $paired[$main] = ($paired[$main] ?? 0) + 1;
                }
            }
        }

        $totals = [
            'income' => 0.0, 'bounty' => 0.0, 'ess' => 0.0, 'estimated' => 0.0, 'corp_tax' => 0.0,
            'ticks' => 0, 'minutes' => 0, 'rats' => $allRats, 'players' => 0, 'characters' => 0,
            'seen_by_corp' => array_sum($seenByCorp), 'paired' => array_sum($paired),
        ];

        foreach ($players as $main => &$p) {
            $p['income'] = round($p['bounty'] + $p['ess'], 2);
            $p['minutes'] = isset($counters[$main]) ? $counters[$main]->minutes() : 0;
            $p['isk_per_hour'] = $p['minutes'] > 0 ? round($p['income'] / ($p['minutes'] / 60), 2) : 0.0;
            $p['rats'] = $rats[$main] ?? 0;
            $p['active_days'] = count($days[$main] ?? []);
            $p['coverage'] = isset($seenByCorp[$main]) ? round(($paired[$main] ?? 0) / $seenByCorp[$main] * 100) : null;
            $p['characters'] = array_keys($p['characters']);
            $p['last_tick'] = isset($last[$main]) ? gmdate('Y-m-d H:i:s', $last[$main]) : null;
            $p['wallet_token'] = (bool) array_filter($p['characters'], fn ($id) => $this->players->hasWalletToken($id));

            foreach (['income', 'bounty', 'ess', 'estimated', 'corp_tax', 'ticks', 'minutes'] as $f) {
                $totals[$f] += $p[$f];
            }
            $totals['characters'] += count($p['characters']);
        }
        unset($p);

        $totals['players'] = count($players);
        $totals['isk_per_hour'] = $totals['minutes'] > 0 ? round($totals['income'] / ($totals['minutes'] / 60), 2) : 0.0;
        $totals['coverage'] = $totals['seen_by_corp'] > 0 ? round($totals['paired'] / $totals['seen_by_corp'] * 100) : null;
        $totals['active_days'] = count(array_replace(...array_values($days ?: [[]])));

        return ['players' => $players, 'totals' => $totals];
    }

    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return (float) ($count % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2);
    }
}
