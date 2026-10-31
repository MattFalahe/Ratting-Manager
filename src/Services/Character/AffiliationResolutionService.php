<?php

namespace RattingManager\Services\Character;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RattingManager\Services\Ratting\Progress;
use RattingManager\Services\Ratting\Summaries;

/**
 * Names and corporations for ratters SeAT does not know.
 *
 * A member who never added a token only appears in the corporation journal,
 * so SeAT has no name or corporation for them anywhere. Everything the plugin
 * knows about those characters comes from here, and pages only ever read it:
 * a character nobody has looked up yet is asked for and shown as in progress,
 * and the lookup runs in the background, so a slow or broken ESI never holds
 * up or breaks a page. This is the same lookup Mining Manager uses.
 *
 * Journal descriptions name most of them for free ("... transferred funds to
 * {name}"), so the import keeps those names first. ESI is asked next, up to
 * 1000 characters a request, and adds the corporation. When ESI is down, or
 * the error budget it shares with SeAT's own jobs is running low, a few
 * characters a run go to EVEWho and then zKillboard instead. Those answers
 * are looked at again sooner, so ESI replaces them on its next good run.
 */
class AffiliationResolutionService
{
    public const TABLE = 'ratting_manager_character_affiliations';

    public const PENDING = 'pending';
    public const INVALID = 'invalid';
    public const JOURNAL = 'journal';

    /** How long an answer from ESI stands before it is looked at again. */
    public const ESI_TTL_HOURS = 24;

    /** Answers coming due this soon go along with the ones due now, so a day of them is one request and not one every ten minutes. */
    public const GATHER_HOURS = 6;

    /** A fallback answer is looked at again sooner, so ESI can replace it. */
    public const FALLBACK_TTL_HOURS = 6;

    /** An id ESI rejects is not sent again for this long. */
    public const INVALID_TTL_DAYS = 30;

    /** The most ids ESI takes in one affiliation or names request. */
    public const ESI_BATCH = 1000;

    /** Characters one run may send to EVEWho and zKillboard, a request each. */
    public const FALLBACK_PER_RUN = 20;

    /**
     * Stop calling ESI once the error budget it reports drops below this. The
     * budget is per IP and shared with SeAT's own authenticated jobs.
     */
    public const ESI_ERROR_BUDGET_FLOOR = 15;

    private const USER_AGENT = 'SeAT Ratting Manager (https://github.com/MattFalahe/Ratting-Manager)';

    /** Set when ESI says the error budget is low: no ESI calls until then. */
    private ?Carbon $esiPausedUntil = null;

    /** Characters asked for while building this page. */
    private array $requested = [];

    // ---------------------------------------------------------------- reading

    /**
     * What has been found for each character, from the table only. Characters
     * still waiting for a first answer are left out; an id ESI rejected comes
     * back with source "invalid".
     *
     * @param array<int, int|string> $characterIds
     * @return array<int, object> character_id => row
     */
    public function known(array $characterIds): array
    {
        $ids = self::ids($characterIds);
        if (!$ids) {
            return [];
        }

        $known = [];
        try {
            foreach (array_chunk($ids, self::ESI_BATCH) as $chunk) {
                $rows = DB::table(self::TABLE)
                    ->whereIn('character_id', $chunk)
                    ->where('source', '!=', self::PENDING)
                    ->get();
                foreach ($rows as $row) {
                    $known[(int) $row->character_id] = $row;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Ratting Manager: could not read looked-up characters: ' . $e->getMessage());
        }

        return $known;
    }

    /**
     * A corporation's name from what is already stored: SeAT's own copy, the
     * names SeAT has seen, then any character already placed in it.
     */
    public function corporationName(?int $corporationId): ?string
    {
        if (!$corporationId) {
            return null;
        }

        try {
            return DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name')
                ?: DB::table('universe_names')->where('entity_id', $corporationId)->where('category', 'corporation')->value('name')
                ?: DB::table(self::TABLE)
                    ->where('corporation_id', $corporationId)
                    ->whereNotNull('corporation_name')
                    ->value('corporation_name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------- asking

    /**
     * Ask for characters a page could not name yet. The next run of
     * ratting-manager:resolve-characters --requested picks them up, within a
     * minute or so.
     *
     * @param array<int, int|string> $characterIds
     */
    public function request(array $characterIds): void
    {
        $ids = self::ids($characterIds);
        if (!$ids) {
            return;
        }

        $this->requested = array_values(array_unique(array_merge($this->requested, $ids)));

        try {
            $have = DB::table(self::TABLE)
                ->whereIn('character_id', $ids)
                ->pluck('character_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $new = array_values(array_diff($ids, $have));

            if ($new) {
                $now = Carbon::now();
                DB::table(self::TABLE)->insertOrIgnore(array_map(fn ($id) => [
                    'character_id' => $id,
                    'source' => self::PENDING,
                    'requested_at' => $now,
                    'expires_at' => $now,
                ], $new));
            }
        } catch (\Throwable $e) {
            Log::warning('Ratting Manager: could not ask for character lookups: ' . $e->getMessage());
        }
    }

    /**
     * Characters asked for while building this page, for the in-progress
     * notice.
     *
     * @return array<int, int>
     */
    public function requestedThisPage(): array
    {
        return $this->requested;
    }

    /**
     * Which of these characters are still waiting for a first answer.
     *
     * @param array<int, int|string> $characterIds
     * @return array<int, int>
     */
    public function stillPending(array $characterIds): array
    {
        $ids = self::ids($characterIds);
        if (!$ids) {
            return [];
        }

        try {
            $answered = DB::table(self::TABLE)
                ->whereIn('character_id', $ids)
                ->where('source', '!=', self::PENDING)
                ->pluck('character_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            return $ids;
        }

        return array_values(array_diff($ids, $answered));
    }

    /**
     * Keep the names journal descriptions gave for characters SeAT has no
     * record of. Nothing is called for them. A character already stored keeps
     * what it has, and only gains a name if it had none; a new one is due for
     * a lookup straight away, so the background run adds its corporation.
     *
     * @param array<int, string> $names character_id => name
     */
    public function rememberJournalNames(array $names): void
    {
        $names = array_filter($names, fn ($name, $id) => (int) $id > 0 && trim((string) $name) !== '', ARRAY_FILTER_USE_BOTH);
        if (!$names) {
            return;
        }

        try {
            $ids = array_map('intval', array_keys($names));
            $inSeat = DB::table('character_infos')->whereIn('character_id', $ids)->pluck('character_id')->map(fn ($id) => (int) $id)->all();
            $stored = DB::table(self::TABLE)->whereIn('character_id', $ids)->get(['character_id', 'character_name', 'source'])->keyBy('character_id');

            $now = Carbon::now();
            $new = [];
            foreach ($names as $id => $name) {
                $id = (int) $id;
                $name = mb_substr(trim((string) $name), 0, 64);

                if (in_array($id, $inSeat, true)) {
                    continue;
                }

                $row = $stored->get($id);
                if ($row === null) {
                    $new[] = [
                        'character_id' => $id,
                        'character_name' => $name,
                        'source' => self::JOURNAL,
                        'resolved_at' => $now,
                        'expires_at' => $now,
                    ];
                } elseif (empty($row->character_name)) {
                    $update = ['character_name' => $name];
                    // A page asked for this one before the import named it, so
                    // it has its name now and stays due for the corporation.
                    if ($row->source === self::PENDING) {
                        $update += ['source' => self::JOURNAL, 'resolved_at' => $now, 'expires_at' => $now];
                    }
                    DB::table(self::TABLE)->where('character_id', $id)->update($update);
                }
            }

            foreach (array_chunk($new, 500) as $chunk) {
                DB::table(self::TABLE)->insertOrIgnore($chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('Ratting Manager: could not keep names from the journal: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------- looking up, background only

    /**
     * Who needs looking up: characters pages asked for first, then ratters of
     * the last year SeAT cannot vouch for. SeAT vouches for a character its
     * member tracking lists, since that comes from the corporation itself and
     * costs no call here. Anyone else is asked about once a day, a revoked
     * token or a stopped SeAT schedule being the reason to look: one request
     * per 1000 characters, gathered so they fall due together.
     *
     * @return array<int, int>
     */
    public function due(bool $requestedOnly = false): array
    {
        $requested = DB::table(self::TABLE)
            ->where('source', self::PENDING)
            ->orderBy('requested_at')
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($requestedOnly) {
            return $requested;
        }

        $ratters = DB::table(Summaries::DAILY)->distinct()->pluck('character_id')->map(fn ($id) => (int) $id)->all();
        $listed = $this->listedByMemberTracking($ratters);

        $inSeat = [];
        $stored = [];
        foreach (array_chunk($ratters, self::ESI_BATCH) as $chunk) {
            foreach (DB::table('character_affiliations')->whereIn('character_id', $chunk)->pluck('character_id') as $id) {
                $inSeat[(int) $id] = true;
            }
            foreach (DB::table(self::TABLE)->whereIn('character_id', $chunk)->where('source', '!=', self::PENDING)->pluck('expires_at', 'character_id') as $id => $expires) {
                $stored[(int) $id] = $expires;
            }
        }

        $now = Carbon::now();
        $soon = $now->copy()->addHours(self::GATHER_HOURS);
        $due = [];
        $comingDue = [];
        foreach ($ratters as $id) {
            if (isset($inSeat[$id], $listed[$id])) {
                continue;
            }

            if (!array_key_exists($id, $stored)) {
                $due[] = $id;
            } elseif ($stored[$id] === null || Carbon::parse($stored[$id])->lte($now)) {
                $due[] = $id;
            } elseif (Carbon::parse($stored[$id])->lte($soon)) {
                $comingDue[] = $id;
            }
        }

        return array_values(array_unique(array_merge($requested, $due, $due === [] ? [] : $comingDue)));
    }

    /**
     * The characters in the member tracking of a corporation SeAT has read in
     * the last day. A corporation that has stopped being read vouches for
     * nobody, since the members it lists may have gone.
     *
     * @param int[] $characters
     * @return array<int, true>
     */
    private function listedByMemberTracking(array $characters): array
    {
        $fresh = DB::table('corporation_member_trackings')
            ->groupBy('corporation_id')
            ->havingRaw('MAX(updated_at) >= ?', [Carbon::now()->subHours(self::ESI_TTL_HOURS)])
            ->pluck('corporation_id')
            ->all();

        $listed = [];
        if ($fresh === []) {
            return $listed;
        }

        foreach (array_chunk($characters, self::ESI_BATCH) as $chunk) {
            foreach (DB::table('corporation_member_trackings')->whereIn('corporation_id', $fresh)->whereIn('character_id', $chunk)->pluck('character_id') as $id) {
                $listed[(int) $id] = true;
            }
        }

        return $listed;
    }

    /**
     * Look characters up and keep what is found. For the scheduled command and
     * other background work only: it calls out to ESI and the fallbacks.
     *
     * @param array<int, int|string> $characterIds
     * @return array{esi: int, fallback: int, invalid: int, left: int}
     */
    public function resolve(array $characterIds, ?Progress $progress = null): array
    {
        $progress ??= Progress::none();
        $ids = self::ids($characterIds);
        $counts = ['esi' => 0, 'fallback' => 0, 'invalid' => 0, 'left' => 0];
        $unanswered = [];

        $progress->step('Character lookups from ESI', count($ids));
        foreach (array_chunk($ids, self::ESI_BATCH) as $chunk) {
            [$found, $invalid, $missed] = $this->esiAffiliations($chunk);
            $names = $found ? $this->esiNames($this->namesNobodyHas($found)) : [];

            foreach ($found as $id => $answer) {
                $this->store($id, [
                    'character_name' => $names[$id] ?? null,
                    'corporation_id' => $answer['corporation_id'],
                    'corporation_name' => $names[$answer['corporation_id']] ?? null,
                    'alliance_id' => $answer['alliance_id'],
                ], 'esi', Carbon::now()->addHours(self::ESI_TTL_HOURS));
                $counts['esi']++;
            }

            foreach ($invalid as $id) {
                $this->store($id, [], self::INVALID, Carbon::now()->addDays(self::INVALID_TTL_DAYS));
                $counts['invalid']++;
            }

            $unanswered = array_merge($unanswered, $missed);
            $progress->advance(count($chunk));
        }

        // Whoever ESI could not answer, a few a run, from the community copies.
        $fallback = array_slice($unanswered, 0, self::FALLBACK_PER_RUN);
        $progress->step('Character lookups from EVEWho and zKillboard', count($fallback));
        foreach ($fallback as $id) {
            $answer = $this->evewho($id) ?? $this->zkillboard($id);
            if ($answer) {
                $this->store($id, $answer, $answer['source'], Carbon::now()->addHours(self::FALLBACK_TTL_HOURS));
                $counts['fallback']++;
            }
            $progress->advance();
        }

        $counts['left'] = count($unanswered) - $counts['fallback'];

        return $counts;
    }

    /**
     * The characters and corporations just answered for whose names are not
     * on file anywhere. Most runs ask about people already named, so most runs
     * make no names request at all.
     *
     * @param array<int, array{corporation_id: int, alliance_id: ?int}> $found
     * @return int[]
     */
    private function namesNobodyHas(array $found): array
    {
        $characters = array_keys($found);
        $named = DB::table('character_infos')->whereIn('character_id', $characters)->pluck('character_id')
            ->merge(DB::table('universe_names')->whereIn('entity_id', $characters)->where('category', 'character')->pluck('entity_id'))
            ->merge(DB::table(self::TABLE)->whereIn('character_id', $characters)->whereNotNull('character_name')->pluck('character_id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $corporations = array_values(array_unique(array_column($found, 'corporation_id')));
        $unnamed = array_filter($corporations, fn ($id) => $this->corporationName($id) === null);

        return array_merge(array_values(array_diff($characters, $named)), $unnamed);
    }

    /**
     * A corporation's name for background work, such as an alert naming a
     * hunter's corporation: what is stored first, then ESI, then EVEWho and
     * zKillboard. EVEWho and zKillboard give a character's corporation as an
     * id only, so this is how one they answered for gets its name. Nothing
     * is kept.
     */
    public function lookUpCorporationName(int $corporationId): ?string
    {
        if ($corporationId <= 0) {
            return null;
        }

        return $this->corporationName($corporationId)
            ?: ($this->esiNames([$corporationId])[$corporationId] ?? null)
            ?: ($this->getJson("https://evewho.com/api/corplist/{$corporationId}")['info'][0]['name'] ?? null)
            ?: ($this->getJson("https://zkillboard.com/api/stats/corporationID/{$corporationId}/", ['Accept-Encoding' => 'gzip'])['info']['name'] ?? null);
    }

    /**
     * Every corporation a character has been in, oldest first, for background
     * work: ESI, then EVEWho, which keeps a copy of the same history.
     *
     * @return array{source: string, records: array<int, array{corporation_id: int, start_date: string}>}|null
     *         null when neither answered
     */
    public function corporationHistory(int $characterId): ?array
    {
        $response = $this->esiGet("/characters/{$characterId}/corporationhistory/");

        if ($response !== null && $response->successful() && ($records = self::historyRecords($response->json())) !== []) {
            return ['source' => 'esi', 'records' => $records];
        }

        $records = self::historyRecords($this->getJson("https://evewho.com/api/character/{$characterId}")['history'] ?? null);

        return $records === [] ? null : ['source' => 'evewho', 'records' => $records];
    }

    /** @return array<int, array{corporation_id: int, start_date: string}> oldest first; empty for anything else than a list of records */
    private static function historyRecords($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $records = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['corporation_id']) || empty($row['start_date'])) {
                continue;
            }

            try {
                $records[] = [
                    'corporation_id' => (int) $row['corporation_id'],
                    'start_date' => Carbon::parse($row['start_date'], 'UTC')->utc()->format('Y-m-d H:i:s'),
                    'record_id' => (int) ($row['record_id'] ?? 0),
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        usort($records, fn ($a, $b) => [$a['start_date'], $a['record_id']] <=> [$b['start_date'], $b['record_id']]);

        return $records;
    }

    /**
     * Drop what is stored for a character, so the next run looks it up again.
     */
    public function forget(int $characterId): void
    {
        try {
            DB::table(self::TABLE)->where('character_id', $characterId)->delete();
        } catch (\Throwable $e) {
            Log::warning("Ratting Manager: could not clear the lookup for character {$characterId}: " . $e->getMessage());
        }
    }

    /**
     * How many characters the table holds, by where the answer came from.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        return DB::table(self::TABLE)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * ESI's affiliations for these characters. One id ESI does not accept
     * fails the whole request with a 400, so the batch is halved until the bad
     * one is alone, and it is marked so it is not sent again.
     *
     * @param array<int, int> $ids
     * @return array{0: array<int, array{corporation_id: int, alliance_id: ?int}>, 1: array<int, int>, 2: array<int, int>}
     *         found, invalid, not answered
     */
    private function esiAffiliations(array $ids): array
    {
        $response = $this->esiPost('/characters/affiliation/', $ids);

        if ($response === null) {
            return [[], [], $ids];
        }

        if ($response->status() === 400) {
            if (count($ids) === 1) {
                return [[], $ids, []];
            }

            $half = intdiv(count($ids), 2);
            [$found1, $invalid1, $missed1] = $this->esiAffiliations(array_slice($ids, 0, $half));
            [$found2, $invalid2, $missed2] = $this->esiAffiliations(array_slice($ids, $half));

            return [$found1 + $found2, array_merge($invalid1, $invalid2), array_merge($missed1, $missed2)];
        }

        if (!$response->successful()) {
            return [[], [], $ids];
        }

        $found = [];
        foreach ((array) $response->json() as $row) {
            if (isset($row['character_id'], $row['corporation_id'])) {
                $found[(int) $row['character_id']] = [
                    'corporation_id' => (int) $row['corporation_id'],
                    'alliance_id' => isset($row['alliance_id']) ? (int) $row['alliance_id'] : null,
                ];
            }
        }

        return [$found, [], array_values(array_diff($ids, array_keys($found)))];
    }

    /**
     * Names for characters and corporations ESI just answered for. A name is
     * nice to have, so a failure here leaves names empty rather than retrying.
     *
     * @param array<int, int> $ids
     * @return array<int, string> id => name
     */
    private function esiNames(array $ids): array
    {
        $response = $this->esiPost('/universe/names/', self::ids($ids));

        if ($response === null || !$response->successful()) {
            return [];
        }

        $names = [];
        foreach ((array) $response->json() as $row) {
            if (isset($row['id'], $row['name'])) {
                $names[(int) $row['id']] = (string) $row['name'];
            }
        }

        return $names;
    }

    /**
     * A tokenless ESI POST. Once ESI says the error budget is low, or is
     * already limiting us, no more calls go out until its error window resets.
     */
    private function esiPost(string $path, array $body)
    {
        return $body ? $this->esi(fn ($http, $url) => $http->post($url, array_values($body)), $path) : null;
    }

    /** A tokenless ESI GET, under the same care as esiPost(). */
    private function esiGet(string $path)
    {
        return $this->esi(fn ($http, $url) => $http->get($url), $path);
    }

    private function esi(callable $send, string $path)
    {
        if ($this->esiPausedUntil && Carbon::now()->lt($this->esiPausedUntil)) {
            return null;
        }

        try {
            $response = $send(
                Http::timeout(15)->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT]),
                rtrim((string) config('ratting-manager.esi_base_url'), '/') . $path . '?datasource=tranquility'
            );
        } catch (\Throwable $e) {
            Log::info('Ratting Manager: ESI did not answer a character lookup: ' . $e->getMessage());

            return null;
        }

        $remain = $response->header('X-Esi-Error-Limit-Remain');
        if ($response->status() === 420 || ($remain !== null && $remain !== '' && (int) $remain < self::ESI_ERROR_BUDGET_FLOOR)) {
            $this->esiPausedUntil = Carbon::now()->addSeconds(max(1, (int) ($response->header('X-Esi-Error-Limit-Reset') ?: 60)));
            Log::info('Ratting Manager: ESI error budget is low, character lookups use the fallbacks until it resets');
        }

        return $response->status() === 420 ? null : $response;
    }

    /**
     * EVEWho's copy of a character: name, corporation and alliance in info[0].
     */
    private function evewho(int $characterId): ?array
    {
        $info = $this->getJson("https://evewho.com/api/character/{$characterId}")['info'][0] ?? null;

        if (!is_array($info) || empty($info['corporation_id'])) {
            return null;
        }

        return [
            'source' => 'evewho',
            'character_name' => $info['name'] ?? null,
            'corporation_id' => (int) $info['corporation_id'],
            'alliance_id' => !empty($info['alliance_id']) ? (int) $info['alliance_id'] : null,
        ];
    }

    /**
     * zKillboard's stats for a character, whose info block carries its name,
     * corporation and alliance. The killmail endpoint has no names, and a
     * killmail's corporation is only as of that kill, so it is not used.
     */
    private function zkillboard(int $characterId): ?array
    {
        $info = $this->getJson("https://zkillboard.com/api/stats/characterID/{$characterId}/", ['Accept-Encoding' => 'gzip'])['info'] ?? null;

        if (!is_array($info) || empty($info['corporationID'])) {
            return null;
        }

        return [
            'source' => 'zkillboard',
            'character_name' => $info['name'] ?? null,
            'corporation_id' => (int) $info['corporationID'],
            'alliance_id' => !empty($info['allianceID']) ? (int) $info['allianceID'] : null,
        ];
    }

    private function getJson(string $url, array $headers = []): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT] + $headers)
                ->get($url);

            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Keep an answer. A name that did not come back this time keeps the one
     * already stored; a corporation always comes with its own name, so a move
     * never leaves the old corporation's name behind.
     */
    private function store(int $characterId, array $answer, string $source, Carbon $expires): void
    {
        $now = Carbon::now();
        $values = [
            'source' => $source,
            'requested_at' => null,
            'resolved_at' => $now,
            'expires_at' => $expires,
        ];

        if (array_key_exists('corporation_id', $answer)) {
            $values['corporation_id'] = $answer['corporation_id'];
            $values['corporation_name'] = $answer['corporation_name'] ?? $this->corporationName($answer['corporation_id']);
            $values['alliance_id'] = $answer['alliance_id'] ?? null;
        }

        if (!empty($answer['character_name'])) {
            $values['character_name'] = mb_substr($answer['character_name'], 0, 64);
        }

        try {
            DB::table(self::TABLE)->updateOrInsert(['character_id' => $characterId], $values);
        } catch (\Throwable $e) {
            Log::warning("Ratting Manager: could not keep the lookup for character {$characterId}: " . $e->getMessage());
        }
    }

    /**
     * @param array<int, int|string|null> $ids
     * @return array<int, int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
    }
}
