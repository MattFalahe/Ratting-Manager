<?php

namespace RattingManager\Services\Ratting;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Npc\NpcRegistry;
use RattingManager\Services\Universe\SolarSystems;

/**
 * The analysis behind My Ratting, the ratter page and the Corporation tab:
 * where, when and how well people rat.
 *
 * Time is split the way it is counted everywhere else. Each tick stands for
 * the 20 minutes before it, a player's overlapping stretches count once, and
 * each tick is credited with the new time it adds. So a weekday, an hour or a
 * system gets exactly its part of the player's hours, and the parts add up
 * to the total.
 *
 * Income by weekday and hour is bounties only, by when they paid out. ESS
 * pays out hours later and in one lump, so putting it on the hour it arrived
 * would credit the wrong hour. ESS does count by system, ISK per hour there
 * included, since each payout names the system its bank was in.
 */
class Insights
{
    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** A system, weekday or hour window needs this much time before its ISK per hour counts as "best". */
    private const MIN_MINUTES_FOR_RATE = 60;

    private Players $players;
    private SolarSystems $systems;

    public function __construct(Players $players, SolarSystems $systems)
    {
        $this->players = $players;
        $this->systems = $systems;
    }

    public function for(Scope $scope, Period $period, bool $corporation): array
    {
        $key = 'insights:' . $scope->cacheKey() . ':' . $period->cacheKey() . ':' . (int) $corporation;

        return FigureCache::remember($key, fn () => $this->compute($scope, $period, $corporation));
    }

    private function compute(Scope $scope, Period $period, bool $corporation): array
    {
        $this->players->load(Ticks::characters($scope, $period->start, $period->end));

        $span = ActiveTime::TICK_MINUTES * 60;
        $coveredUntil = [];
        $mains = [];

        $weekdaySeconds = array_fill(0, 7, 0);
        $weekdayBounty = array_fill(0, 7, 0.0);
        $hourSeconds = array_fill(0, 24, 0);
        $hourBounty = array_fill(0, 24, 0.0);
        $system = [];
        // A kill group is one second, one system and one kill list, and the
        // ticks come oldest first, so only the current second's groups need
        // remembering to count each group once.
        $groupsSeen = [];
        $groupSecond = null;
        $rats = 0;
        $days = [];
        $weekdayDays = array_fill(0, 7, []);
        $playersByDay = [];
        $playersBySlot = [];
        $playerSeconds = [];

        $ticks = Ticks::stream($scope, $period->start, $period->end, ['character_id', 'solar_system_id', 'net_amount', 'kill_group', 'npc_kills'], true);
        foreach ($ticks as $t) {
            $time = (int) $t->ts;
            $main = $mains[(int) $t->character_id] ??= $this->players->mainOf((int) $t->character_id);
            $sys = (int) $t->solar_system_id;

            // The new time this tick adds to its player's ratting.
            $until = $coveredUntil[$main] ?? null;
            $start = $time - $span;
            if ($until === null || $start >= $until) {
                $added = $span;
            } elseif ($time > $until) {
                $added = $time - $until;
            } else {
                $added = 0;
            }
            $coveredUntil[$main] = max($until ?? $time, $time);

            // Days and hours counted from the epoch, which began on a Thursday.
            $day = intdiv($time, 86400);
            $slot = intdiv($time, 3600);
            $dow = ($day + 3) % 7;
            $hour = $slot % 24;
            $net = (float) $t->net_amount;

            $weekdaySeconds[$dow] += $added;
            $weekdayBounty[$dow] += $net;
            $hourSeconds[$hour] += $added;
            $hourBounty[$hour] += $net;
            $playerSeconds[$main] = ($playerSeconds[$main] ?? 0) + $added;

            $system[$sys] ??= ['seconds' => 0, 'bounty' => 0.0, 'ess' => 0.0, 'ticks' => 0, 'rats' => 0, 'players' => [], 'days' => [], 'last' => null, 'playerSeconds' => []];
            $system[$sys]['seconds'] += $added;
            $system[$sys]['bounty'] += $net;
            $system[$sys]['ticks']++;
            $system[$sys]['players'][$main] = true;
            $system[$sys]['days'][$day] = true;
            $system[$sys]['last'] = $time;
            $system[$sys]['playerSeconds'][$main] = ($system[$sys]['playerSeconds'][$main] ?? 0) + $added;

            if ($time !== $groupSecond) {
                $groupSecond = $time;
                $groupsSeen = [];
            }
            if ($t->kill_group !== null && !isset($groupsSeen[$t->kill_group])) {
                $groupsSeen[$t->kill_group] = true;
                $rats += (int) $t->npc_kills;
                $system[$sys]['rats'] += (int) $t->npc_kills;
            }

            $days[$day] = true;
            $weekdayDays[$dow][$day] = true;
            $playersByDay[$day][$main] = true;
            $playersBySlot[$slot][$main] = true;
        }

        $ess = Summaries::between($scope, $period->start, $period->end)
            ->groupBy('solar_system_id')
            ->selectRaw('solar_system_id, SUM(ess_net) as net')
            ->pluck('net', 'solar_system_id')
            ->filter(fn ($net) => (float) $net !== 0.0);
        foreach ($ess as $sys => $net) {
            $sys = (int) $sys;
            $system[$sys] ??= ['seconds' => 0, 'bounty' => 0.0, 'ess' => 0.0, 'ticks' => 0, 'rats' => 0, 'players' => [], 'days' => [], 'last' => null, 'playerSeconds' => []];
            $system[$sys]['ess'] += (float) $net;
        }

        $totalSeconds = array_sum($playerSeconds);
        $totalBounty = array_sum($weekdayBounty);
        $totalEss = (float) $ess->sum();
        $totalIncome = $totalBounty + $totalEss;

        $systems = $this->systemRows($system, $totalSeconds, $totalIncome);
        $weekdays = $this->weekdayRows($weekdaySeconds, $weekdayBounty, $weekdayDays, $playersByDay);
        $hours = $this->hourRows($hourSeconds, $hourBounty, $playersBySlot);
        $sessions = $this->sessions($scope, $period);
        $npcs = $this->topNpcs($scope, $period);

        $highlights = [
            'favourite_system' => $this->pick($systems, 'minutes'),
            'best_system' => $this->pick(array_filter($systems, fn ($s) => $s['minutes'] >= self::MIN_MINUTES_FOR_RATE && $s['system_id'] > 0), 'isk_per_hour'),
            'busiest_weekday' => $this->pick($weekdays, 'minutes'),
            'best_weekday' => $this->pick(array_filter($weekdays, fn ($w) => $w['minutes'] >= self::MIN_MINUTES_FOR_RATE), 'isk_per_hour'),
            'busiest_window' => $this->bestWindow($hourSeconds, $hourBounty, false),
            'best_window' => $this->bestWindow($hourSeconds, $hourBounty, true),
            'favourite_faction' => $npcs['favourite_faction'],
            'rats_per_hour' => $totalSeconds > 0 ? round($rats / ($totalSeconds / 3600), 1) : 0.0,
            'ess_share' => $totalIncome > 0 ? round($totalEss / $totalIncome * 100) : null,
            'active_days' => count($days),
            'income_per_active_day' => count($days) ? round($totalIncome / count($days), 2) : 0.0,
            'sessions' => $sessions,
        ];

        if ($corporation) {
            $peak = null;
            foreach ($playersBySlot as $slot => $set) {
                if ($peak === null || count($set) > $peak['players']) {
                    $peak = ['players' => count($set), 'when' => gmdate('Y-m-d H:00', $slot * 3600)];
                }
            }
            $highlights['peak_concurrent'] = $peak;
            $highlights['players_per_day'] = count($playersByDay)
                ? round(array_sum(array_map('count', $playersByDay)) / count($playersByDay), 1)
                : 0.0;
            $highlights['income_per_player'] = count($playerSeconds) ? round($totalIncome / count($playerSeconds), 2) : 0.0;
        } else {
            $highlights += $this->streaks(array_keys($days));
        }

        return [
            'highlights' => $highlights,
            'systems' => $systems,
            'weekdays' => $weekdays,
            'hours' => $hours,
            'npcs' => $npcs['top'],
            'heatmap' => $this->heatmap($playersBySlot, $corporation),
            'totals' => [
                'minutes' => intdiv($totalSeconds + 30, 60),
                'bounty' => round($totalBounty, 2),
                'ess' => round($totalEss, 2),
                'income' => round($totalIncome, 2),
                'rats' => $rats,
            ],
        ];
    }

    private function systemRows(array $system, int $totalSeconds, float $totalIncome): array
    {
        $details = $this->systems->details(array_filter(array_keys($system)));

        $rows = [];
        foreach ($system as $id => $s) {
            $income = $s['bounty'] + $s['ess'];
            $minutes = intdiv($s['seconds'] + 30, 60);

            $topMain = null;
            if ($s['playerSeconds']) {
                arsort($s['playerSeconds']);
                $topMain = (int) array_key_first($s['playerSeconds']);
            }

            $rows[] = [
                'system_id' => $id,
                'name' => $id ? ($details[$id]['name'] ?? 'System #' . $id) : trans('ratting-manager::common.system_not_given'),
                'security' => $id ? ($details[$id]['security'] ?? null) : null,
                'minutes' => $minutes,
                'income' => round($income, 2),
                'bounty' => round($s['bounty'], 2),
                'ess' => round($s['ess'], 2),
                'isk_per_hour' => $minutes > 0 ? round($income / ($minutes / 60), 2) : 0.0,
                'ticks' => $s['ticks'],
                'rats' => $s['rats'],
                'players' => count($s['players']),
                'active_days' => count($s['days']),
                'last' => $s['last'] === null ? null : gmdate('Y-m-d H:i:s', $s['last']),
                'top_main' => $topMain,
                'hours_share' => $totalSeconds > 0 ? round($s['seconds'] / $totalSeconds * 100, 1) : 0.0,
                'income_share' => $totalIncome > 0 ? round($income / $totalIncome * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['minutes'], $b['income']] <=> [$a['minutes'], $a['income']]);

        return $rows;
    }

    private function weekdayRows(array $seconds, array $bounty, array $weekdayDays, array $playersByDay): array
    {
        $rows = [];
        foreach (self::WEEKDAYS as $i => $name) {
            $minutes = intdiv($seconds[$i] + 30, 60);
            $days = count($weekdayDays[$i]);
            $players = 0;
            foreach (array_keys($weekdayDays[$i]) as $day) {
                $players += count($playersByDay[$day] ?? []);
            }

            $rows[] = [
                'index' => $i,
                'name' => $name,
                'short' => substr($name, 0, 3),
                'minutes' => $minutes,
                'bounty' => round($bounty[$i], 2),
                'isk_per_hour' => $minutes > 0 ? round($bounty[$i] / ($minutes / 60), 2) : 0.0,
                'active_days' => $days,
                'players_per_day' => $days ? round($players / $days, 1) : 0.0,
            ];
        }

        return $rows;
    }

    private function hourRows(array $seconds, array $bounty, array $playersBySlot): array
    {
        $playersByHour = array_fill(0, 24, 0);
        foreach ($playersBySlot as $slot => $set) {
            $playersByHour[$slot % 24] += count($set);
        }

        $rows = [];
        for ($h = 0; $h < 24; $h++) {
            $minutes = intdiv($seconds[$h] + 30, 60);
            $rows[] = [
                'hour' => $h,
                'label' => sprintf('%02d:00', $h),
                'minutes' => $minutes,
                'bounty' => round($bounty[$h], 2),
                'isk_per_hour' => $minutes > 0 ? round($bounty[$h] / ($minutes / 60), 2) : 0.0,
                'player_hours' => $playersByHour[$h],
            ];
        }

        return $rows;
    }

    /**
     * The three-hour stretch of the day, in EVE time, with the most ratting,
     * or with the best bounty ISK per hour. Wraps past midnight.
     */
    private function bestWindow(array $seconds, array $bounty, bool $byRate): ?array
    {
        $best = null;
        for ($h = 0; $h < 24; $h++) {
            $s = $seconds[$h] + $seconds[($h + 1) % 24] + $seconds[($h + 2) % 24];
            $b = $bounty[$h] + $bounty[($h + 1) % 24] + $bounty[($h + 2) % 24];
            $minutes = intdiv($s + 30, 60);

            if ($minutes === 0 || ($byRate && $minutes < self::MIN_MINUTES_FOR_RATE)) {
                continue;
            }

            $rate = round($b / ($minutes / 60), 2);
            $score = $byRate ? $rate : $minutes;
            if ($best === null || $score > $best['score']) {
                $best = [
                    'score' => $score,
                    'label' => sprintf('%02d:00-%02d:00', $h, ($h + 3) % 24),
                    'minutes' => $minutes,
                    'isk_per_hour' => $rate,
                ];
            }
        }

        if ($best) {
            unset($best['score']);
        }

        return $best;
    }

    /** Session habits: how many, how long, and the longest. */
    private function sessions(Scope $scope, Period $period): array
    {
        $minutes = $scope->apply(DB::table('ratting_manager_sessions'))
            ->whereBetween('started_at', [$period->start->copy()->subMinutes(ActiveTime::TICK_MINUTES), $period->end])
            ->orderBy('minutes')
            ->pluck('minutes')
            ->map(fn ($m) => (int) $m)
            ->all();

        $count = count($minutes);

        return [
            'count' => $count,
            'median_minutes' => $count ? $minutes[intdiv($count, 2)] : 0,
            'longest_minutes' => $count ? max($minutes) : 0,
        ];
    }

    /** The NPCs killed most often, a fleet's shared payout counted once. */
    private function topNpcs(Scope $scope, Period $period): array
    {
        $rows = Ticks::kills($scope, $period->start, $period->end);

        $byFaction = [];
        foreach ($rows as $r) {
            $byFaction[$r['faction']] = ($byFaction[$r['faction']] ?? 0) + $r['quantity'];
        }
        arsort($byFaction);
        $favourite = array_key_first($byFaction);

        return [
            'top' => array_map(fn ($r) => [
                'type_id' => $r['npc_type_id'],
                'name' => $r['name'] ?? ('Type ' . $r['npc_type_id']),
                'faction' => $r['faction'],
                'size' => $r['size'],
                'rank' => $r['rank'],
                'killed' => $r['quantity'],
            ], array_slice($rows, 0, 10)),
            'favourite_faction' => $favourite === null ? null : [
                'key' => $favourite,
                'name' => NpcRegistry::FACTIONS[$favourite] ?? ucfirst($favourite),
                'killed' => $byFaction[$favourite],
            ],
        ];
    }

    /**
     * When the ratting happens, weekday by hour in EVE time. For one player a
     * cell counts the hours they ratted in it; for a corporation, the players
     * ratting in it, added up over the period.
     *
     * @param array<int, array<int, true>> $playersBySlot hour since the epoch => players ratting in it
     */
    private function heatmap(array $playersBySlot, bool $perPlayer): array
    {
        $cells = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($playersBySlot as $slot => $set) {
            $cells[(intdiv($slot, 24) + 3) % 7][$slot % 24] += $perPlayer ? count($set) : 1;
        }

        $max = max(array_map('max', $cells)) ?: 0;
        $levels = [];
        foreach ($cells as $day => $hours) {
            foreach ($hours as $hour => $value) {
                $levels[$day][$hour] = $value === 0 || $max === 0 ? 0 : (int) max(1, ceil($value / $max * 5));
            }
        }

        return ['values' => $cells, 'levels' => $levels, 'max' => $max];
    }

    /**
     * Days in a row with at least one tick: the longest run in the period,
     * and the run still going, which counts today or yesterday as current.
     *
     * @param int[] $days days since the epoch
     */
    private function streaks(array $days): array
    {
        if ($days === []) {
            return ['longest_streak' => 0, 'current_streak' => 0];
        }

        sort($days);
        $longest = 1;
        $run = 1;
        for ($i = 1; $i < count($days); $i++) {
            $run = $days[$i - 1] + 1 === $days[$i] ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        $today = intdiv(now()->getTimestamp(), 86400);
        $current = end($days) >= $today - 1 ? $run : 0;

        return ['longest_streak' => $longest, 'current_streak' => $current];
    }

    private function pick(array $rows, string $field): ?array
    {
        $best = null;
        foreach ($rows as $row) {
            if (($row[$field] ?? 0) > 0 && ($best === null || $row[$field] > $best[$field])) {
                $best = $row;
            }
        }

        return $best;
    }
}
