<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Models\Tick;
use RattingManager\Services\Configuration\Settings;

/**
 * Groups each character's ticks into sessions: a run of ticks with no gap
 * longer than the session gap.
 *
 * A session can start before the window being rebuilt, so the rebuild for a
 * character begins at the first tick of whatever session was still open
 * when the window starts.
 */
class SessionBuilder
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /** How many characters rebuild() will go through, for a progress bar. */
    public function characterCount(Carbon $since, ?array $characters = null): int
    {
        if ($characters !== null) {
            return count($characters);
        }

        return Tick::where('ticked_at', '>=', $since)->distinct()->count('character_id');
    }

    /** The session gap in minutes, as the builder uses it. */
    public function gapMinutes(): int
    {
        return max(20, (int) $this->settings->get('data.session_gap_minutes'));
    }

    /**
     * @param int[]|null $characters only these characters, or null for every
     *                               character with a tick since $since
     */
    public function rebuild(Carbon $since, ?Progress $progress = null, ?array $characters = null): int
    {
        $progress ??= Progress::none();
        $gap = $this->gapMinutes() * 60;

        $since = RawTicks::clamp($since);

        $characters ??= Tick::where('ticked_at', '>=', $since)
            ->distinct()
            ->pluck('character_id')
            ->all();

        $built = 0;
        foreach ($characters as $characterId) {
            $built += $this->rebuildCharacter((int) $characterId, $since, $gap);
            $progress->advance();
        }

        return $built;
    }

    private function rebuildCharacter(int $characterId, Carbon $since, int $gap): int
    {
        $from = $since->copy();

        $open = DB::table('ratting_manager_sessions')
            ->where('character_id', $characterId)
            ->where('ended_at', '>=', $since->copy()->subSeconds($gap))
            ->where('started_at', '<', $since)
            ->orderBy('started_at')
            ->first(['started_at', 'ended_at']);

        if ($open !== null) {
            // A session open across the line has lost its earlier ticks, so
            // building it again would shorten it. It stays as it was built.
            $from = Carbon::parse($open->started_at)->lt(RawTicks::firstHeld())
                ? Carbon::parse($open->ended_at)->addSecond()
                : Carbon::parse($open->started_at);
        }
        $linkFrom = $from->lt($since) ? $from : $since;

        // Plain rows, read one at a time in time order, each folded into the
        // session it belongs to as it arrives. Only the running totals of the
        // session being built are held, never the ticks themselves, so a
        // character with a year of ticks costs no more memory than one with
        // a day of them.
        $ticks = DB::table('ratting_manager_ticks')
            ->where('character_id', $characterId)
            ->where('ticked_at', '>=', $from)
            ->orderBy('ticked_at')
            ->select(['corporation_id', 'solar_system_id', 'ticked_at', 'net_amount', 'net_is_estimated', 'npc_kills'])
            ->selectRaw("TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', ticked_at) as ts")
            ->cursor();

        $now = now();
        $sessions = [];
        $current = null;
        $last = null;

        foreach ($ticks as $tick) {
            $t = (int) $tick->ts;
            if ($current !== null && $t - $last > $gap) {
                $sessions[] = $this->finish($characterId, $current, $now);
                $current = null;
            }
            $current ??= $this->begin($tick);
            $this->add($current, $tick, $t);
            $last = $t;
        }
        if ($current !== null) {
            $sessions[] = $this->finish($characterId, $current, $now);
        }

        DB::transaction(function () use ($characterId, $from, $linkFrom, $sessions) {
            $old = DB::table('ratting_manager_sessions')
                ->where('character_id', $characterId)
                ->where('ended_at', '>=', $from)
                ->pluck('id')
                ->all();

            foreach (array_chunk($old, 1000) as $chunk) {
                DB::table('ratting_manager_ticks')->whereIn('session_id', $chunk)->update(['session_id' => null]);
                DB::table('ratting_manager_sessions')->whereIn('id', $chunk)->delete();
            }

            foreach (array_chunk($sessions, 500) as $chunk) {
                DB::table('ratting_manager_sessions')->insert($chunk);
            }

            if ($sessions === [] && $linkFrom->gte($from)) {
                return;
            }

            // Every tick of a session lies between its first tick (started_at
            // plus the tick length) and its last (ended_at), and sessions are
            // apart by more than the gap, so one update by time sets every
            // tick's session at once.
            DB::table('ratting_manager_ticks as t')
                ->join('ratting_manager_sessions as s', function ($join) {
                    $join->on('s.character_id', '=', 't.character_id')
                        ->whereRaw('t.ticked_at >= DATE_ADD(s.started_at, INTERVAL ' . ActiveTime::TICK_MINUTES . ' MINUTE)')
                        ->whereColumn('t.ticked_at', '<=', 's.ended_at');
                })
                ->where('t.character_id', $characterId)
                ->where('t.ticked_at', '>=', $linkFrom)
                ->where('s.ended_at', '>=', $linkFrom)
                ->update(['t.session_id' => DB::raw('s.id')]);
        });

        return count($sessions);
    }

    /** A new session, opened by its first tick. */
    private function begin(object $tick): array
    {
        return [
            'corporation_id' => (int) $tick->corporation_id,
            'first' => (string) $tick->ticked_at,
            'last' => (string) $tick->ticked_at,
            'time' => new ActiveTimeCounter(),
            'ticks' => 0,
            'net' => 0.0,
            'estimated' => 0.0,
            'kills' => 0,
            'systems' => [],
        ];
    }

    private function add(array &$session, object $tick, int $timestamp): void
    {
        $session['last'] = (string) $tick->ticked_at;
        $session['time']->add($timestamp);
        $session['ticks']++;
        $session['net'] += (float) $tick->net_amount;
        if ((bool) $tick->net_is_estimated) {
            $session['estimated'] += (float) $tick->net_amount;
        }
        $session['kills'] += (int) $tick->npc_kills;
        $system = (int) $tick->solar_system_id;
        $session['systems'][$system] = ($session['systems'][$system] ?? 0) + 1;
    }

    /** The session's row, with the system it has most ticks in as its primary one. */
    private function finish(int $characterId, array $session, Carbon $now): array
    {
        $minutes = $session['time']->minutes();
        $net = $session['net'];

        // Most ticks first; a tie keeps the system seen first, as arsort()
        // is stable.
        $bySystem = $session['systems'];
        arsort($bySystem);

        return [
            'character_id' => $characterId,
            'corporation_id' => $session['corporation_id'],
            'started_at' => Carbon::parse($session['first'])->subMinutes(ActiveTime::TICK_MINUTES)->format('Y-m-d H:i:s'),
            'ended_at' => substr($session['last'], 0, 19),
            'minutes' => $minutes,
            'tick_count' => $session['ticks'],
            'primary_system_id' => (int) array_key_first($bySystem),
            'system_count' => count($bySystem),
            'net_amount' => round($net, 2),
            'estimated_amount' => round($session['estimated'], 2),
            'npc_kills' => $session['kills'],
            'isk_per_hour' => $minutes > 0 ? round($net / ($minutes / 60), 2) : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
