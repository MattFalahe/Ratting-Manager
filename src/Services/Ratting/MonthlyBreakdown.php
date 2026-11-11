<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use RattingManager\Services\Character\Players;

/**
 * Month by month figures: per character for a member, per month for the
 * corporation.
 */
class MonthlyBreakdown
{
    private Players $players;

    public function __construct(Players $players)
    {
        $this->players = $players;
    }

    /** @return array<string, string> 'YYYY-MM' => 'October 2026', newest first, for a month picker */
    public static function monthChoices(int $months = 12): array
    {
        $choices = [];
        for ($m = now()->startOfMonth(), $i = 0; $i < $months; $i++, $m->subMonthNoOverflow()) {
            $choices[$m->format('Y-m')] = $m->format('F Y');
        }

        return $choices;
    }

    public static function month(?string $value): Carbon
    {
        $choices = self::monthChoices();

        return isset($choices[(string) $value])
            ? Carbon::parse($value . '-01')->startOfMonth()
            : now()->startOfMonth();
    }

    /**
     * What each character earned in a month, with the player's total. The
     * total's time counts alts ratting together once, so it can be less than
     * the characters' times added up.
     */
    public function byCharacter(Scope $scope, Carbon $month): array
    {
        $result = FigureCache::remember(
            'month-by-character:' . $scope->cacheKey() . ':' . $month->format('Y-m') . ':' . now()->format('Y-m'),
            fn () => $this->computeByCharacter($scope, $month)
        );

        // Names go on after the cache, as RattingMetrics::collect() does.
        $this->players->load(array_column($result['rows'], 'character_id'));
        foreach ($result['rows'] as &$row) {
            $row['name'] = $this->players->name($row['character_id']);
        }
        unset($row);

        return $result;
    }

    private function computeByCharacter(Scope $scope, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $money = Summaries::between($scope, $start, $end)
            ->groupBy('character_id')
            ->selectRaw('character_id, SUM(bounty_net) as bounty, SUM(ess_net) as ess, SUM(bounty_estimated + ess_estimated) as estimated, SUM(bounty_corp_tax + ess_corp_tax) as corp_tax, SUM(ticks) as ticks')
            ->get()
            ->keyBy('character_id');

        // Oldest first, counted as the ticks arrive; see RattingMetrics for
        // why only the current second's kill groups need remembering.
        $times = [];
        $rats = [];
        $allTime = new ActiveTimeCounter();
        $allRats = 0;
        $second = null;
        $groupsThisSecond = [];
        $characterGroupsThisSecond = [];
        foreach (Ticks::stream($scope, $start, $end, ['character_id', 'kill_group', 'npc_kills'], true) as $t) {
            $id = (int) $t->character_id;
            $ts = (int) $t->ts;
            ($times[$id] ??= new ActiveTimeCounter())->add($ts);
            $allTime->add($ts);
            if ($ts !== $second) {
                $second = $ts;
                $groupsThisSecond = [];
                $characterGroupsThisSecond = [];
            }
            if ($t->kill_group !== null) {
                if (!isset($characterGroupsThisSecond[$id][$t->kill_group])) {
                    $characterGroupsThisSecond[$id][$t->kill_group] = true;
                    $rats[$id] = ($rats[$id] ?? 0) + (int) $t->npc_kills;
                }
                if (!isset($groupsThisSecond[$t->kill_group])) {
                    $groupsThisSecond[$t->kill_group] = true;
                    $allRats += (int) $t->npc_kills;
                }
            }
        }

        $ids = array_values(array_unique(array_merge($money->keys()->map(fn ($id) => (int) $id)->all(), array_keys($times))));
        $this->players->load($ids);

        $rows = [];
        foreach ($ids as $id) {
            $m = $money->get($id);
            $income = (float) ($m->bounty ?? 0) + (float) ($m->ess ?? 0);
            $minutes = isset($times[$id]) ? $times[$id]->minutes() : 0;
            $rows[] = [
                'character_id' => $id,
                'name' => $this->players->name($id),
                'bounty' => round((float) ($m->bounty ?? 0), 2),
                'ess' => round((float) ($m->ess ?? 0), 2),
                'income' => round($income, 2),
                'estimated' => round((float) ($m->estimated ?? 0), 2),
                'corp_tax' => round((float) ($m->corp_tax ?? 0), 2),
                'ticks' => (int) ($m->ticks ?? 0),
                'minutes' => $minutes,
                'isk_per_hour' => $minutes > 0 ? round($income / ($minutes / 60), 2) : 0.0,
                'rats' => $rats[$id] ?? 0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['income'] <=> $a['income']);

        $total = [
            'bounty' => round(array_sum(array_column($rows, 'bounty')), 2),
            'ess' => round(array_sum(array_column($rows, 'ess')), 2),
            'income' => round(array_sum(array_column($rows, 'income')), 2),
            'estimated' => round(array_sum(array_column($rows, 'estimated')), 2),
            'corp_tax' => round(array_sum(array_column($rows, 'corp_tax')), 2),
            'ticks' => array_sum(array_column($rows, 'ticks')),
            'minutes' => $allTime->minutes(),
            'rats' => $allRats,
        ];
        $total['isk_per_hour'] = $total['minutes'] > 0 ? round($total['income'] / ($total['minutes'] / 60), 2) : 0.0;

        return ['month' => $start->format('F Y'), 'rows' => $rows, 'total' => $total];
    }

    /**
     * Income per character for each of the last months, newest first, with
     * the month's total.
     */
    public function characterMatrix(Scope $scope, int $months = 12): array
    {
        $result = FigureCache::remember(
            'character-matrix:' . $scope->cacheKey() . ':' . $months . ':' . now()->format('Y-m'),
            fn () => $this->computeCharacterMatrix($scope, $months)
        );

        // Names go on after the cache, as RattingMetrics::collect() does.
        $this->players->load(array_column($result['characters'], 'id'));
        foreach ($result['characters'] as &$character) {
            $character['name'] = $this->players->name($character['id']);
        }
        unset($character);

        return $result;
    }

    private function computeCharacterMatrix(Scope $scope, int $months): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $rows = Summaries::months($scope, $start->format('Y-m'))
            ->groupBy('character_id', 'month')
            ->selectRaw('character_id, month, SUM(bounty_net + ess_net) as income')
            ->get();

        $characters = $rows->pluck('character_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->players->load($characters);

        $byMonth = [];
        $byCharacter = [];
        foreach ($rows as $r) {
            $byMonth[$r->month][(int) $r->character_id] = round((float) $r->income, 2);
            $byCharacter[(int) $r->character_id] = ($byCharacter[(int) $r->character_id] ?? 0) + (float) $r->income;
        }
        arsort($byCharacter);

        $matrix = [];
        foreach (array_keys(self::monthChoices($months)) as $key) {
            $cells = $byMonth[$key] ?? [];
            $matrix[] = [
                'month' => Carbon::parse($key . '-01')->format('M Y'),
                'cells' => $cells,
                'total' => round(array_sum($cells), 2),
            ];
        }

        // Newest first; the months before the first ratting are left out.
        $active = array_keys(array_filter($matrix, fn ($m) => $m['cells'] !== []));
        $matrix = $active === [] ? [] : array_slice($matrix, 0, max($active) + 1);

        return [
            'characters' => array_map(fn ($id) => ['id' => $id, 'name' => $this->players->name($id)], array_keys($byCharacter)),
            'months' => $matrix,
        ];
    }

    /**
     * The corporation's last months: what its players earned, what landed in
     * the corporation's wallet, who ratted and for how long.
     */
    public function corporationMonths(Scope $scope, int $months = 12): array
    {
        return FigureCache::remember(
            'months:' . $scope->cacheKey() . ':' . $months . ':' . now()->format('Y-m'),
            fn () => $this->computeCorporationMonths($scope, $months)
        );
    }

    private function computeCorporationMonths(Scope $scope, int $months): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $money = Summaries::months($scope, $start->format('Y-m'))
            ->groupBy('month')
            ->selectRaw('month, SUM(bounty_net) as bounty, SUM(ess_net) as ess, SUM(bounty_estimated + ess_estimated) as estimated, SUM(bounty_corp_tax + ess_corp_tax) as corp_tax')
            ->get()
            ->keyBy('month');

        $end = now();
        $this->players->load(Ticks::characters($scope, $start, $end));

        $times = [];
        $characters = [];
        $mains = [];
        $monthOf = [];
        // Oldest first, so each player's time in each month is counted as
        // the ticks arrive instead of keeping a year of timestamps.
        foreach (Ticks::stream($scope, $start, $end, ['character_id'], true) as $t) {
            $time = (int) $t->ts;
            $key = $monthOf[intdiv($time, 86400)] ??= gmdate('Y-m', $time);
            $main = $mains[(int) $t->character_id] ??= $this->players->mainOf((int) $t->character_id);
            ($times[$key][$main] ??= new ActiveTimeCounter())->add($time);
            $characters[$key][(int) $t->character_id] = true;
        }

        $rows = [];
        foreach (array_keys(self::monthChoices($months)) as $key) {
            $m = $money->get($key);
            $income = (float) ($m->bounty ?? 0) + (float) ($m->ess ?? 0);
            $tax = (float) ($m->corp_tax ?? 0);
            $minutes = array_sum(array_map(fn (ActiveTimeCounter $c) => $c->minutes(), $times[$key] ?? []));
            $players = count($times[$key] ?? []);

            $rows[] = [
                'month' => Carbon::parse($key . '-01')->format('F Y'),
                'label' => Carbon::parse($key . '-01')->format('M Y'),
                'income' => round($income, 2),
                'bounty' => round((float) ($m->bounty ?? 0), 2),
                'ess' => round((float) ($m->ess ?? 0), 2),
                'estimated' => round((float) ($m->estimated ?? 0), 2),
                'corp_tax' => round($tax, 2),
                'tax_share' => ($income + $tax) > 0 ? round($tax / ($income + $tax) * 100, 1) : null,
                'players' => $players,
                'characters' => count($characters[$key] ?? []),
                'minutes' => $minutes,
                'isk_per_hour' => $minutes > 0 ? round($income / ($minutes / 60), 2) : 0.0,
                'income_per_player' => $players ? round($income / $players, 2) : 0.0,
            ];
        }

        return $rows;
    }
}
