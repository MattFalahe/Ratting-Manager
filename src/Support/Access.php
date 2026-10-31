<?php

namespace RattingManager\Support;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;

/**
 * Where the signed-in user sits in the four tiers.
 *
 * SeAT checks one permission at a time, so a director holding only the
 * director permission would fail a plain can:ratting-manager.member check.
 * Every route and page asks here instead, and a tier includes everything
 * below it.
 */
final class Access
{
    public const TIERS = ['view', 'member', 'director', 'admin'];

    public static function atLeast(string $tier, $user = null): bool
    {
        $user = $user ?? auth()->user();
        $rank = array_search($tier, self::TIERS, true);

        if (!$user || $rank === false) {
            return false;
        }

        foreach (array_slice(self::TIERS, $rank) as $held) {
            if ($user->can('ratting-manager.' . $held)) {
                return true;
            }
        }

        return false;
    }

    public static function isDirector($user = null): bool
    {
        return self::atLeast('director', $user);
    }

    public static function isAdmin($user = null): bool
    {
        return self::atLeast('admin', $user);
    }

    /**
     * The tracked corporations a user's characters are in now.
     *
     * @return int[]
     */
    public static function trackedCorporationsOf($user = null): array
    {
        $user = $user ?? auth()->user();
        $tracked = app(Settings::class)->configuredCorporations();
        $characters = $user ? app(Players::class)->charactersOfUser($user) : [];

        if ($tracked === [] || $characters === []) {
            return [];
        }

        $now = DB::table('character_affiliations')
            ->whereIn('character_id', $characters)
            ->pluck('corporation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($tracked, $now));
    }

    /**
     * The corporations a user may read another player's figures for: every
     * tracked one for an admin, and for a director the ones their characters
     * are in now. A role is given once and stays, so this is what stops a
     * director reading a corporation they have nothing to do with, or one
     * they have since left.
     *
     * @return int[]
     */
    public static function directorCorporations($user = null): array
    {
        return self::isAdmin($user)
            ? app(Settings::class)->configuredCorporations()
            : (self::isDirector($user) ? self::trackedCorporationsOf($user) : []);
    }

    /** Whether the Corporation tab and the director's pages have anything to show this user. */
    public static function seesCorporations($user = null): bool
    {
        return self::isDirector($user) && self::directorCorporations($user) !== [];
    }

    /**
     * Whether the switch under Settings closes the ratting pages to this user:
     * it is on, and none of their characters is in a tracked corporation now.
     * Admins are never closed out, or nobody could switch it back.
     */
    public static function closedOut($user = null): bool
    {
        $settings = app(Settings::class);

        return $settings->get('access.members_only')
            && !self::isAdmin($user)
            && $settings->configuredCorporations() !== []
            && self::trackedCorporationsOf($user) === [];
    }
}
