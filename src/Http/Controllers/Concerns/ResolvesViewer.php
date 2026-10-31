<?php

namespace RattingManager\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Support\Access;

trait ResolvesViewer
{
    /** @return int[] */
    protected function viewerCharacters(Players $players): array
    {
        return $players->charactersOfUser(auth()->user());
    }

    protected function viewerMain(Players $players): int
    {
        $user = auth()->user();
        $main = (int) ($user->main_character_id ?? 0);
        $characters = $this->viewerCharacters($players);

        return $main > 0 ? $main : (int) ($characters[0] ?? 0);
    }

    /**
     * The configured corporations these characters are in now, or every
     * configured corporation when they are in none.
     *
     * @param int[] $characters
     * @return int[]
     */
    protected function corporationsOf(array $characters, Settings $settings): array
    {
        $configured = $settings->configuredCorporations();

        $own = $characters === [] ? [] : DB::table('character_affiliations')
            ->whereIn('character_id', $characters)
            ->pluck('corporation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $mine = array_values(array_intersect($configured, $own));

        return $mine !== [] ? $mine : $configured;
    }

    /**
     * Names for the corporation picker: the corporations this viewer may
     * read, or the ones given.
     *
     * @param int[]|null $corporations
     * @return array<int, string>
     */
    protected function corporationNames(?array $corporations = null): array
    {
        $ids = $corporations ?? Access::directorCorporations();
        $names = DB::table('corporation_infos')->whereIn('corporation_id', $ids ?: [0])->pluck('name', 'corporation_id')->all();

        $list = [];
        foreach ($ids as $id) {
            $list[$id] = $names[$id] ?? ('Corporation ' . $id);
        }

        return $list;
    }

    /**
     * A player's home among these corporations: the one their main is in
     * now, or else the first of them any of their characters is in. Null
     * when they are in none of them.
     *
     * @param int[] $characters
     * @param int[] $corporations
     */
    protected function homeCorporation(array $characters, int $main, array $corporations): ?int
    {
        if ($corporations === []) {
            return null;
        }

        $now = DB::table('character_affiliations')
            ->whereIn('character_id', array_values(array_unique(array_merge($characters, [$main]))))
            ->pluck('corporation_id', 'character_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (isset($now[$main]) && in_array($now[$main], $corporations, true)) {
            return $now[$main];
        }

        foreach ($corporations as $id) {
            if (in_array($id, $now, true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * One corporation out of these, or all of them: the one the picker sent,
     * all of them for All, and otherwise the viewer's own, their main's
     * corporation first. A viewer in none of them starts on all of them.
     *
     * @param int[] $choices
     * @return int[]
     */
    protected function pickFrom(array $choices, $request, Players $players): array
    {
        if (count($choices) < 2) {
            return $choices;
        }

        $picked = (string) $request->query('corporation', '');
        if ($picked === Access::ALL_CORPORATIONS) {
            return $choices;
        }
        if (in_array((int) $picked, $choices, true)) {
            return [(int) $picked];
        }

        $own = $this->homeCorporation($this->viewerCharacters($players), $this->viewerMain($players), $choices);

        return $own !== null ? [$own] : $choices;
    }

    /**
     * The corporations a member's own board covers, and the ones they can
     * pick between: the tracked corporations their characters are in now,
     * their main's picked first. In none of them, every tracked corporation
     * stands in and there is nothing to pick.
     *
     * @return array{0: int[], 1: int[]} the board's corporations, then the choices
     */
    protected function memberCorporations($request, Players $players, Settings $settings): array
    {
        $own = Access::trackedCorporationsOf();
        if ($own === []) {
            return [$settings->configuredCorporations(), []];
        }

        return [$this->pickFrom($own, $request, $players), $own];
    }

    /**
     * The corporations this viewer may read, for a page that shows other
     * players' figures. A director with none has nothing to see there.
     *
     * @return int[]
     */
    protected function readableCorporations(Settings $settings): array
    {
        if ($settings->configuredCorporations() === []) {
            return [];
        }

        $readable = Access::directorCorporations();
        abort_if($readable === [], 403, trans('ratting-manager::common.no_corporation_to_read'));

        return $readable;
    }

    /** @return int[] the corporations a director's page shows: their own by default, or the one or all they picked */
    protected function pickedCorporations($request, Settings $settings): array
    {
        return $this->pickFrom($this->readableCorporations($settings), $request, app(Players::class));
    }

    /**
     * Stops a director opening a player who has nothing to do with the
     * corporations they may read: one who never ratted for them and is not in
     * them now. What the player did elsewhere is cut by the page's scope.
     *
     * @param int[] $characters
     * @param int[] $corporations
     */
    protected function abortUnlessReadable(array $characters, array $corporations): void
    {
        if (Access::isAdmin()) {
            return;
        }

        $ratted = DB::table('ratting_manager_daily_summaries')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->whereIn('character_id', $characters ?: [0])
            ->exists();

        abort_unless($ratted || DB::table('character_affiliations')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->whereIn('character_id', $characters ?: [0])
            ->exists(), 404);
    }
}
