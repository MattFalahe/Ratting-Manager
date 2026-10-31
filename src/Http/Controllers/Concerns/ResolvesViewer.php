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
     * The configured corporations the viewer's characters are in now. A
     * member's leaderboard is their own corporation's; when none of their
     * characters is in one, the whole configured list stands in.
     *
     * @return int[]
     */
    protected function viewerCorporations(Players $players, Settings $settings): array
    {
        return $this->corporationsOf($this->viewerCharacters($players), $settings);
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
     * Names of the corporations this viewer may read, for the corporation picker.
     *
     * @return array<int, string>
     */
    protected function corporationNames(): array
    {
        $readable = Access::directorCorporations();
        $names = DB::table('corporation_infos')->whereIn('corporation_id', $readable ?: [0])->pluck('name', 'corporation_id')->all();

        $list = [];
        foreach ($readable as $id) {
            $list[$id] = $names[$id] ?? ('Corporation ' . $id);
        }

        return $list;
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

    /** @return int[] the corporations a director asked for: one they may read, or all of them */
    protected function pickedCorporations($request, Settings $settings): array
    {
        $readable = $this->readableCorporations($settings);
        $picked = (int) $request->query('corporation', 0);

        return in_array($picked, $readable, true) ? [$picked] : $readable;
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
