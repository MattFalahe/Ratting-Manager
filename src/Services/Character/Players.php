<?php

namespace RattingManager\Services\Character;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Ratting\Ticks;

/**
 * Who a character belongs to, and what to call them.
 *
 * A player is a SeAT user: every character with a token on that user, shown
 * under the user's main. A character SeAT holds no token for is a player of
 * its own. Lookups are batched, so a leaderboard of a hundred players costs a
 * handful of queries.
 *
 * Names come from SeAT first, then the plugin's lookups. A character nobody
 * has named yet is asked for and shown as in progress; the background lookup
 * fills it in, and the page refreshes itself once it has.
 */
class Players
{
    public const WALLET_SCOPE = 'esi-wallet.read_character_wallet.v1';

    /** None of the player's characters was ever added to SeAT. */
    public const AUTH_NEVER = 'never';

    /** The player had tokens on SeAT and has none left. */
    public const AUTH_LOST = 'lost';

    /** @var array<int, ?int> character_id => user_id */
    private array $userOf = [];

    /** @var array<int, int> user_id => main character_id */
    private array $mainOf = [];

    /** @var array<int, int[]> user_id => character ids */
    private array $charactersOf = [];

    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, true> characters this page showed as in progress */
    private array $pending = [];

    /** @var array<int, true> looked up, but no name was found anywhere */
    private array $unnamed = [];

    private AffiliationResolutionService $lookups;
    private CurrentCorporations $current;

    public function __construct(AffiliationResolutionService $lookups, CurrentCorporations $current)
    {
        $this->lookups = $lookups;
        $this->current = $current;
    }

    /** @var array<int, bool> character_id => holds a live token with the wallet scope */
    private array $wallet = [];

    /** @var array<int, bool> character_id => whether its token on SeAT is live, with no entry for a character with no token on file */
    private array $liveToken = [];

    /** @param int[] $characterIds */
    public function load(array $characterIds): void
    {
        // Callers pass a column of ticks, so the same id comes thousands of
        // times; each one is looked up once.
        $missing = [];
        foreach ($characterIds as $id) {
            $id = (int) $id;
            if (!array_key_exists($id, $this->userOf)) {
                $missing[$id] = $id;
            }
        }
        $missing = array_values($missing);
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunk) {
            foreach ($chunk as $id) {
                $this->userOf[$id] = null;
                $this->wallet[$id] = false;
            }

            // Deleted tokens still say whose character it was, so a player who
            // removed a token keeps their history together.
            $tokens = DB::table('refresh_tokens')
                ->whereIn('character_id', $chunk)
                ->get(['character_id', 'user_id', 'scopes', 'deleted_at']);

            foreach ($tokens as $token) {
                $this->userOf[(int) $token->character_id] = $token->user_id ? (int) $token->user_id : null;
                $scopes = json_decode((string) $token->scopes, true) ?: [];
                $this->wallet[(int) $token->character_id] = $token->deleted_at === null
                    && in_array(self::WALLET_SCOPE, $scopes, true);
                $this->liveToken[(int) $token->character_id] = $token->deleted_at === null;
            }
        }

        $this->loadUsers(array_filter(array_unique(array_map(fn ($id) => $this->userOf[$id], $missing))));

        // Names in the same batch, but nobody is asked for until a page shows
        // them: a chart that only groups characters by player names nobody.
        $this->loadNames(array_merge($missing, array_values($this->mainOf)), false);
    }

    /** The character a player is shown under: the user's main, or the character itself. */
    public function mainOf(int $characterId): int
    {
        if (!array_key_exists($characterId, $this->userOf)) {
            $this->load([$characterId]);
        }
        $user = $this->userOf[$characterId];

        return $user && isset($this->mainOf[$user]) ? $this->mainOf[$user] : $characterId;
    }

    /** @return int[] every character of the player this character belongs to */
    public function charactersOfPlayer(int $characterId): array
    {
        $this->load([$characterId]);
        $user = $this->userOf[$characterId];

        return $user ? ($this->charactersOf[$user] ?? [$characterId]) : [$characterId];
    }

    /** @return int[] */
    public function charactersOfUser($user): array
    {
        if (!$user) {
            return [];
        }

        $this->loadUsers([(int) $user->id]);

        return $this->charactersOf[(int) $user->id] ?? [];
    }

    public function name(int $characterId): string
    {
        $this->loadNames([$characterId]);

        if (isset($this->names[$characterId])) {
            return $this->names[$characterId];
        }

        return isset($this->pending[$characterId])
            ? trans('ratting-manager::common.character_pending')
            : 'Character #' . $characterId;
    }

    /**
     * The name, or "Character #123" while it is still being looked up. For
     * text that is kept or sent, where "in progress" would outlive the lookup
     * it describes.
     */
    public function nameOrId(int $characterId): string
    {
        $this->loadNames([$characterId]);

        return $this->names[$characterId] ?? ('Character #' . $characterId);
    }

    /** Session key: the characters pages showed this user as in progress. */
    public const SHOWN_PENDING = 'ratting-manager.shown-pending';

    /**
     * Characters this request showed as in progress, for the notice that
     * refreshes the page once they are in. They are noted in the session too,
     * since the page's watcher may only ask about characters it was shown.
     *
     * @return int[]
     */
    public function shownPending(): array
    {
        $ids = array_keys($this->pending);

        if ($ids !== [] && request()->hasSession()) {
            $seen = (array) request()->session()->get(self::SHOWN_PENDING, []);
            request()->session()->put(self::SHOWN_PENDING, array_slice(array_values(array_unique(array_merge($ids, $seen))), 0, 2000));
        }

        return $ids;
    }

    /**
     * Mark what a page shows beside a player: who has left, and who SeAT holds
     * no token for.
     *
     * Ratting counts for the corporation a character was in at the time, so a
     * player who leaves stays on the boards for the periods they ratted, and
     * the mark says why. A player has left when any of their characters'
     * corporations is known now, from SeAT or the lookups, and none of them is
     * in the corporations shown. A player nobody knows the corporation of is
     * not marked.
     *
     * The time is when the last of their characters left, once the background
     * lookup has it. left_exact is false for a time that is only when the move
     * was first known of. Their last tick is read from what landed for these
     * corporations, so it stands whenever SeAT got round to the journal.
     *
     * @param array<int, array> $rows player rows with main_id and characters
     * @param int[] $tracked the corporations the page is showing
     * @return array<int, array> the rows, each with left, left_at, left_exact, left_last_tick and seat_auth
     */
    public function markStanding(array $rows, array $tracked): array
    {
        $ofRow = [];
        foreach ($rows as $i => $row) {
            $ofRow[$i] = array_map('intval', array_merge($row['characters'] ?? [], [$row['main_id'] ?? 0]));
        }
        $this->load(array_merge([], ...array_values($ofRow)));

        // Every character of the player counts, not only the ones that
        // ratted: a main still in the corporation means they have not left.
        foreach ($ofRow as $i => $ids) {
            $all = $ids;
            foreach ($ids as $id) {
                $user = $this->userOf[$id] ?? null;
                if ($user) {
                    $all = array_merge($all, $this->charactersOf[$user] ?? []);
                }
            }
            $ofRow[$i] = array_values(array_unique(array_filter($all)));
        }

        $everyone = array_values(array_unique(array_merge([], ...array_values($ofRow))));
        $now = array_map(fn ($answer) => $answer['corporation_id'], $this->current->of($everyone));

        $departures = [];
        foreach (array_chunk($everyone, 1000) as $chunk) {
            $found = DB::table(Departures::TABLE)
                ->whereIn('character_id', $chunk)
                ->whereIn('corporation_id', $tracked ?: [0])
                ->orderBy('left_at')
                ->get(['character_id', 'left_at', 'source']);
            foreach ($found as $departure) {
                $departures[(int) $departure->character_id] = $departure;
            }
        }

        $gone = [];
        foreach ($rows as $i => &$row) {
            $known = array_values(array_intersect_key($now, array_flip($ofRow[$i])));
            $row['left'] = $known !== [] && array_intersect($known, $tracked) === [];
            $row['left_at'] = null;
            $row['left_exact'] = false;
            $row['left_last_tick'] = null;
            $row['seat_auth'] = $this->seatAuth($ofRow[$i]);

            if ($row['left']) {
                array_push($gone, ...$ofRow[$i]);
                foreach (array_intersect_key($departures, array_flip($ofRow[$i])) as $departure) {
                    if ($row['left_at'] === null || $departure->left_at > $row['left_at']) {
                        $row['left_at'] = (string) $departure->left_at;
                        $row['left_exact'] = $departure->source !== Departures::SEEN;
                    }
                }
            }
        }
        unset($row);

        if ($gone !== []) {
            $lastTicks = Ticks::lastTicks(array_values(array_unique($gone)), $tracked);
            foreach ($rows as $i => &$row) {
                if ($row['left']) {
                    $row['left_last_tick'] = max(array_intersect_key($lastTicks, array_flip($ofRow[$i])) ?: [null]);
                }
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * @param int[] $characters every character of one player
     * @return string|null AUTH_NEVER, AUTH_LOST, or null while any of them holds a live token
     */
    private function seatAuth(array $characters): ?string
    {
        $hadOne = false;
        foreach ($characters as $id) {
            if ($this->liveToken[$id] ?? false) {
                return null;
            }
            $hadOne = $hadOne || array_key_exists($id, $this->liveToken);
        }

        return $hadOne ? self::AUTH_LOST : self::AUTH_NEVER;
    }

    /** The same for one character, for a page listing them one by one. */
    public function characterAuth(int $characterId): ?string
    {
        $this->load([$characterId]);

        return $this->seatAuth([$characterId]);
    }

    /** The corporation the character is in now, by the newest answer on file. */
    public function currentCorporationName(int $characterId): ?string
    {
        $corporationId = $this->current->of([$characterId])[$characterId]['corporation_id'] ?? null;
        if ($corporationId === null) {
            return null;
        }

        $row = $this->lookups->known([$characterId])[$characterId] ?? null;

        return $this->lookups->corporationName($corporationId)
            ?? (($row && (int) $row->corporation_id === $corporationId) ? $row->corporation_name : null)
            ?? ('Corporation ' . $corporationId);
    }

    public function hasWalletToken(int $characterId): bool
    {
        if (!array_key_exists($characterId, $this->userOf)) {
            $this->load([$characterId]);
        }

        return $this->wallet[$characterId];
    }

    public function isKnownToSeat(int $characterId): bool
    {
        $this->load([$characterId]);

        return $this->userOf[$characterId] !== null;
    }

    /** @param int[] $userIds */
    private function loadUsers(array $userIds): void
    {
        $missing = array_values(array_diff(array_map('intval', $userIds), array_keys($this->charactersOf)));
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunk) {
            $mains = DB::table('users')->whereIn('id', $chunk)->pluck('main_character_id', 'id');

            $tokens = DB::table('refresh_tokens')->whereIn('user_id', $chunk)->get(['character_id', 'user_id', 'scopes', 'deleted_at']);

            foreach ($chunk as $user) {
                $this->charactersOf[$user] = [];
            }

            foreach ($tokens as $token) {
                $id = (int) $token->character_id;
                $user = (int) $token->user_id;
                $this->charactersOf[$user][] = $id;
                $this->userOf[$id] = $user;
                $scopes = json_decode((string) $token->scopes, true) ?: [];
                $this->wallet[$id] = $token->deleted_at === null && in_array(self::WALLET_SCOPE, $scopes, true);
                $this->liveToken[$id] = $token->deleted_at === null;
            }

            foreach ($chunk as $user) {
                $main = (int) ($mains[$user] ?? 0);
                $this->mainOf[$user] = $main > 0 ? $main : ($this->charactersOf[$user][0] ?? 0);
            }
        }
    }

    /**
     * @param int[] $characterIds
     * @param bool $ask whether a character nobody has named yet is asked for
     *                  and shown as in progress, or left for when it is shown
     */
    private function loadNames(array $characterIds, bool $ask = true): void
    {
        $missing = array_values(array_filter(
            array_unique(array_map('intval', $characterIds)),
            fn ($id) => $id > 0 && !isset($this->names[$id]) && !isset($this->pending[$id]) && !isset($this->unnamed[$id])
        ));
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunk) {
            $this->names += DB::table('character_infos')->whereIn('character_id', $chunk)->pluck('name', 'character_id')->all();

            // Names SeAT has seen for characters it holds no record of.
            $left = array_values(array_diff($chunk, array_keys($this->names)));
            if ($left !== []) {
                $this->names += DB::table('universe_names')
                    ->whereIn('entity_id', $left)
                    ->where('category', 'character')
                    ->pluck('name', 'entity_id')
                    ->all();
            }

            $left = array_values(array_diff($chunk, array_keys($this->names)));
            if ($left === []) {
                continue;
            }

            $known = $this->lookups->known($left);
            $toAsk = [];
            foreach ($left as $id) {
                $row = $known[$id] ?? null;
                if ($row === null) {
                    if ($ask) {
                        $toAsk[] = $id;
                        $this->pending[$id] = true;
                    }
                } elseif (!empty($row->character_name)) {
                    $this->names[$id] = $row->character_name;
                } else {
                    $this->unnamed[$id] = true;
                }
            }

            $this->lookups->request($toAsk);
        }
    }
}
