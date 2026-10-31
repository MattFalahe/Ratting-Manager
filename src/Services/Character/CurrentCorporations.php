<?php

namespace RattingManager\Services\Character;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The corporation each character is in now.
 *
 * SeAT refreshes character_affiliations every two hours from a public ESI call,
 * so a revoked token does not stop it, but its updated_at only moves when the
 * corporation changes: it says how long the answer has stood, not when it was
 * last asked. The lookups say when they asked, so the two can be put in order
 * and the newest answer wins.
 *
 * EVEWho and zKillboard can be days behind EVE, so one of theirs only overrules
 * SeAT once SeAT's answer has stood for a week.
 */
class CurrentCorporations
{
    private const TRUST_SLOW_SOURCES_AFTER_DAYS = 7;

    private AffiliationResolutionService $lookups;

    public function __construct(AffiliationResolutionService $lookups)
    {
        $this->lookups = $lookups;
    }

    /**
     * @param int[] $characters
     * @return array<int, array{corporation_id: int, as_of: ?string}> character_id => the answer and how long it is known to have stood; nobody knows the ones left out
     */
    public function of(array $characters): array
    {
        $seat = [];
        foreach (array_chunk(array_values(array_unique($characters)), 1000) as $chunk) {
            foreach (DB::table('character_affiliations')->whereIn('character_id', $chunk)->get(['character_id', 'corporation_id', 'updated_at']) as $row) {
                $seat[(int) $row->character_id] = ['corporation_id' => (int) $row->corporation_id, 'changed_at' => $row->updated_at];
            }
        }

        $lookups = [];
        foreach ($this->lookups->known($characters) as $id => $row) {
            if ($row->corporation_id) {
                $lookups[(int) $id] = ['corporation_id' => (int) $row->corporation_id, 'source' => (string) $row->source, 'asked_at' => $row->resolved_at];
            }
        }

        $now = Carbon::now();
        $answers = [];
        foreach ($seat + $lookups as $id => $unused) {
            $answer = self::newest($seat[$id] ?? null, $lookups[$id] ?? null, $now);
            if ($answer !== null) {
                $answers[$id] = $answer;
            }
        }

        return $answers;
    }

    /**
     * @param array{corporation_id: int, changed_at: ?string}|null $seat
     * @param array{corporation_id: int, source: string, asked_at: ?string}|null $lookup
     * @return array{corporation_id: int, as_of: ?string}|null
     */
    public static function newest(?array $seat, ?array $lookup, Carbon $now): ?array
    {
        $fromSeat = $seat === null ? null : ['corporation_id' => $seat['corporation_id'], 'as_of' => $seat['changed_at']];
        $fromLookup = $lookup === null ? null : ['corporation_id' => $lookup['corporation_id'], 'as_of' => $lookup['asked_at']];

        if ($seat === null || $lookup === null || $seat['corporation_id'] === $lookup['corporation_id']) {
            return $fromSeat ?? $fromLookup;
        }

        $seatChanged = $seat['changed_at'] === null ? null : Carbon::parse($seat['changed_at']);
        $asked = $lookup['asked_at'] === null ? null : Carbon::parse($lookup['asked_at']);

        if ($asked === null || ($seatChanged !== null && $seatChanged->gte($asked))) {
            return $fromSeat;
        }

        if ($lookup['source'] === 'esi') {
            return $fromLookup;
        }

        return $seatChanged === null || $seatChanged->lt($now->copy()->subDays(self::TRUST_SLOW_SOURCES_AFTER_DAYS))
            ? $fromLookup
            : $fromSeat;
    }
}
