<?php

namespace RattingManager\Services\Character;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;

/**
 * Exactly when a character left a tracked corporation.
 *
 * EVE moves a character straight from one corporation to the next, so the time
 * a corporation history starts its next row is the time they left. SeAT's copy
 * is read first and needs no call, then ESI and EVEWho for the characters SeAT
 * has no token for. Only characters who have moved are looked up, and each
 * move once, so the first run on a big install spreads over a few hours.
 *
 * When no history answers, the time the move was noticed is kept, marked as
 * such, and asked about again for a while in case they were only down.
 */
class Departures
{
    public const TABLE = 'ratting_manager_departures';

    public const SEEN = 'seen';

    private const PER_RUN = 100;

    private const RETRY_HOURS = 6;

    private const RETRY_DAYS = 7;

    private Settings $settings;
    private AffiliationResolutionService $lookups;
    private CurrentCorporations $current;

    public function __construct(Settings $settings, AffiliationResolutionService $lookups, CurrentCorporations $current)
    {
        $this->settings = $settings;
        $this->lookups = $lookups;
        $this->current = $current;
    }

    /**
     * Look up the characters who have moved and whose move is not on file.
     *
     * @return array{exact: int, seen: int, left: int}
     */
    public function track(): array
    {
        $due = $this->due();
        $counts = ['exact' => 0, 'seen' => 0, 'left' => max(0, count($due) - self::PER_RUN)];

        foreach (array_slice($due, 0, self::PER_RUN) as $move) {
            $found = $this->fromHistory($move);

            if ($found !== null) {
                DB::transaction(function () use ($move, $found) {
                    DB::table(self::TABLE)->where('character_id', $move['character_id'])->where('corporation_id', $move['corporation_id'])->where('source', self::SEEN)->delete();
                    $this->store($move, $found['left_at'], $found['next_corporation_id'], $found['source']);
                });
                $counts['exact']++;
                continue;
            }

            $this->noticed($move);
            $counts['seen']++;
        }

        return $counts;
    }

    /**
     * The characters who ratted for a tracked corporation and are somewhere
     * else now, newest first, whose move is not on file. A move on file from
     * before their last ratting there is an older one: they came back and have
     * gone again.
     *
     * @return array<int, array{character_id: int, corporation_id: int, last_day: string, now: int, as_of: ?string}>
     */
    public function due(): array
    {
        $tracked = $this->settings->configuredCorporations();
        if ($tracked === []) {
            return [];
        }

        $rows = DB::table('ratting_manager_daily_summaries')
            ->whereIn('corporation_id', $tracked)
            ->groupBy('character_id', 'corporation_id')
            ->selectRaw('character_id, corporation_id, MAX(date) as last_day')
            ->get();

        $characters = $rows->pluck('character_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $now = $this->current->of($characters);

        $stored = [];
        foreach (array_chunk($characters, 1000) as $chunk) {
            foreach (DB::table(self::TABLE)->whereIn('character_id', $chunk)->orderBy('left_at')->get() as $row) {
                $stored[$row->character_id . ':' . $row->corporation_id] = $row;
            }
        }

        $due = [];
        foreach ($rows as $row) {
            $id = (int) $row->character_id;
            $corporation = (int) $row->corporation_id;
            $lastDay = substr((string) $row->last_day, 0, 10);

            if (!isset($now[$id]) || $now[$id]['corporation_id'] === $corporation) {
                continue;
            }

            $known = $stored[$id . ':' . $corporation] ?? null;
            if ($known !== null && substr((string) $known->left_at, 0, 10) >= $lastDay && !$this->worthAskingAgain($known)) {
                continue;
            }

            $due[] = ['character_id' => $id, 'corporation_id' => $corporation, 'last_day' => $lastDay, 'now' => $now[$id]['corporation_id'], 'as_of' => $now[$id]['as_of']];
        }

        usort($due, fn ($a, $b) => strcmp($b['last_day'], $a['last_day']));

        return $due;
    }

    /** A move only noticed, while its history might still answer. */
    private function worthAskingAgain(object $known): bool
    {
        return $known->source === self::SEEN
            && Carbon::parse($known->updated_at)->lt(now()->subHours(self::RETRY_HOURS))
            && Carbon::parse($known->created_at)->gt(now()->subDays(self::RETRY_DAYS));
    }

    /** @return array{left_at: string, next_corporation_id: int, source: string}|null */
    private function fromHistory(array $move): ?array
    {
        $seat = DB::table('character_corporation_histories')
            ->where('character_id', $move['character_id'])
            ->orderBy('start_date')
            ->orderBy('record_id')
            ->get(['corporation_id', 'start_date'])
            ->map(fn ($r) => ['corporation_id' => (int) $r->corporation_id, 'start_date' => (string) $r->start_date])
            ->all();

        // SeAT's copy may not have caught up with the move yet, so one that
        // does not show it goes on to the live answer.
        if (($found = self::leftAfter($seat, $move['corporation_id'], $move['last_day'])) !== null) {
            return $found + ['source' => 'seat'];
        }

        $answer = $this->lookups->corporationHistory($move['character_id']);
        if ($answer === null) {
            return null;
        }

        $found = self::leftAfter($answer['records'], $move['corporation_id'], $move['last_day']);

        return $found === null ? null : $found + ['source' => $answer['source']];
    }

    /**
     * When a character last left this corporation, on or after a day they were
     * still ratting for it.
     *
     * @param array<int, array{corporation_id: int, start_date: string}> $history oldest first
     * @return array{left_at: string, next_corporation_id: int}|null
     */
    public static function leftAfter(array $history, int $corporationId, string $lastDay): ?array
    {
        $found = null;

        foreach ($history as $i => $record) {
            $next = $history[$i + 1] ?? null;

            if ($next !== null && $record['corporation_id'] === $corporationId && substr($next['start_date'], 0, 10) >= $lastDay) {
                $found = ['left_at' => $next['start_date'], 'next_corporation_id' => $next['corporation_id']];
            }
        }

        return $found;
    }

    /**
     * No history answered: keep when the move was first known of, or note that
     * it was asked about again. SeAT's row says when SeAT saw the change, which
     * can be well before this run on an install new to this.
     */
    private function noticed(array $move): void
    {
        $known = DB::table(self::TABLE)
            ->where('character_id', $move['character_id'])
            ->where('corporation_id', $move['corporation_id'])
            ->where('source', self::SEEN)
            ->where('left_at', '>=', $move['last_day'])
            ->exists();

        if ($known) {
            DB::table(self::TABLE)
                ->where('character_id', $move['character_id'])
                ->where('corporation_id', $move['corporation_id'])
                ->where('source', self::SEEN)
                ->update(['updated_at' => now()]);

            return;
        }

        $asOf = $move['as_of'] ? Carbon::parse($move['as_of'])->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

        $this->store($move, max($asOf, $move['last_day'] . ' 00:00:00'), $move['now'], self::SEEN);
    }

    private function store(array $move, string $leftAt, int $nextCorporation, string $source): void
    {
        DB::table(self::TABLE)->insertOrIgnore([
            'character_id' => $move['character_id'],
            'corporation_id' => $move['corporation_id'],
            'left_at' => $leftAt,
            'next_corporation_id' => $nextCorporation,
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
