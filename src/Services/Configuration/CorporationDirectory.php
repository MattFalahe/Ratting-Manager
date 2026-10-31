<?php

namespace RattingManager\Services\Configuration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Ratting\CharacterDays;
use RattingManager\Services\Ratting\Summaries;

/**
 * The corporations SeAT knows, for picking which to track: a light list to
 * search, and the details of one to check before tracking it.
 */
class CorporationDirectory
{
    private const CORPORATION_WALLET_SCOPE = 'esi-wallet.read_corporation_wallets.v1';
    private const BOUNTY_REF_TYPES = ['bounty_prizes', 'bounty_prize'];

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Every corporation SeAT has, with how many of its characters are on
     * SeAT, for the search list.
     *
     * @return array<int, array{id: int, name: string, ticker: string, alliance: ?string, on_seat: int, tracked: bool}>
     */
    public function all(): array
    {
        $onSeat = $this->liveTokens()
            ->join('character_affiliations as a', 'a.character_id', '=', 't.character_id')
            ->groupBy('a.corporation_id')
            ->selectRaw('a.corporation_id, COUNT(DISTINCT t.character_id) as n')
            ->pluck('n', 'corporation_id');

        $alliances = $this->allianceTickers();
        $tracked = array_flip($this->settings->configuredCorporations());

        return DB::table('corporation_infos')
            ->orderBy('name')
            ->get(['corporation_id', 'name', 'ticker', 'alliance_id'])
            ->map(fn ($c) => [
                'id' => (int) $c->corporation_id,
                'name' => (string) $c->name,
                'ticker' => (string) $c->ticker,
                'alliance' => $c->alliance_id ? ($alliances[$c->alliance_id] ?? null) : null,
                'on_seat' => (int) ($onSeat[$c->corporation_id] ?? 0),
                'tracked' => isset($tracked[(int) $c->corporation_id]),
            ])
            ->all();
    }

    /** Everything worth knowing about one corporation before tracking it. */
    public function details(int $corporationId): ?array
    {
        $corp = DB::table('corporation_infos')->where('corporation_id', $corporationId)->first();
        if ($corp === null) {
            return null;
        }

        $characters = DB::table('character_affiliations')->where('corporation_id', $corporationId)->pluck('character_id');
        $tokens = collect();
        foreach ($characters->chunk(1000) as $chunk) {
            $tokens = $tokens->merge($this->liveTokens()->whereIn('t.character_id', $chunk->all())->get(['t.character_id', 't.user_id', 't.scopes']));
        }

        $walletTokens = 0;
        $corporationWalletTokens = 0;
        foreach ($tokens as $token) {
            $scopes = json_decode((string) $token->scopes, true) ?: [];
            $walletTokens += in_array(Players::WALLET_SCOPE, $scopes, true) ? 1 : 0;
            $corporationWalletTokens += in_array(self::CORPORATION_WALLET_SCOPE, $scopes, true) ? 1 : 0;
        }

        // The newest row, and how many in the days ESI serves. Counting or taking
        // the newest of a corporation's whole journal reads every row it has,
        // on every load of Settings.
        $bounties = fn () => DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->whereIn('ref_type', self::BOUNTY_REF_TYPES);
        $lastBounty = $bounties()->orderByDesc('date')->limit(1)->value('date');
        $recentBounties = $lastBounty === null ? 0 : $bounties()->where('date', '>=', now()->subDays(30))->count();

        $alliance = null;
        if ($corp->alliance_id) {
            $row = Schema::hasTable('alliances') ? DB::table('alliances')->where('alliance_id', $corp->alliance_id)->first(['name', 'ticker']) : null;
            $alliance = [
                'id' => (int) $corp->alliance_id,
                'name' => $row->name ?? DB::table('universe_names')->where('entity_id', $corp->alliance_id)->value('name') ?? 'Alliance #' . $corp->alliance_id,
                'ticker' => $row->ticker ?? null,
            ];
        }

        $onSeat = $tokens->pluck('character_id')->unique()->count();
        $members = (int) $corp->member_count;

        return [
            'id' => (int) $corp->corporation_id,
            'name' => (string) $corp->name,
            'ticker' => (string) $corp->ticker,
            'logo' => 'https://images.evetech.net/corporations/' . (int) $corp->corporation_id . '/logo?size=64',
            'alliance' => $alliance,
            'ceo' => $corp->ceo_id ? $this->characterName((int) $corp->ceo_id) : null,
            'founded' => $corp->date_founded ? substr((string) $corp->date_founded, 0, 10) : null,
            'tax_rate' => $corp->tax_rate !== null ? round((float) $corp->tax_rate * 100, 1) : null,
            'members' => $members,
            'on_seat' => $onSeat,
            'on_seat_share' => $members > 0 ? min(100, (int) round($onSeat / $members * 100)) : null,
            'players' => $tokens->pluck('user_id')->filter()->unique()->count(),
            'wallet_tokens' => $walletTokens,
            'corporation_wallet_tokens' => $corporationWalletTokens,
            'bounty_rows' => $recentBounties,
            'last_bounty' => $lastBounty,
            // The character days hold ticks that have been dropped as well.
            'ticks' => Summaries::ready()
                ? (int) DB::table(CharacterDays::TABLE)->where('corporation_id', $corporationId)->sum('tick_count')
                : DB::table('ratting_manager_ticks')->where('corporation_id', $corporationId)->count(),
            'tracked' => in_array((int) $corp->corporation_id, $this->settings->configuredCorporations(), true),
        ];
    }

    /** Tokens SeAT still holds, not the ones removed. */
    private function liveTokens()
    {
        return DB::table('refresh_tokens as t')->whereNull('t.deleted_at');
    }

    /** @return array<int, string> alliance_id => ticker */
    private function allianceTickers(): array
    {
        if (!Schema::hasTable('alliances')) {
            return [];
        }

        return DB::table('alliances')->whereNotNull('ticker')->pluck('ticker', 'alliance_id')->all();
    }

    private function characterName(int $characterId): string
    {
        return DB::table('character_infos')->where('character_id', $characterId)->value('name')
            ?? DB::table('universe_names')->where('entity_id', $characterId)->value('name')
            ?? 'Character #' . $characterId;
    }
}
