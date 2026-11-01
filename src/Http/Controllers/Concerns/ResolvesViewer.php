<?php

namespace RattingManager\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Summaries;
use RattingManager\Support\Access;
use RattingManager\Support\Query;

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
     * corporation first. All is only ever picked on purpose, so no page opens
     * on corporations added together: a viewer in none of them starts on the
     * first.
     *
     * @param int[] $choices
     * @return int[]
     */
    protected function pickFrom(array $choices, $request, Players $players): array
    {
        return $this->pickOr($choices, $request, fn () => $this->homeCorporation($this->viewerCharacters($players), $this->viewerMain($players), $choices));
    }

    /**
     * @param int[] $choices
     * @param callable(): ?int $home where to open when the picker sent nothing usable
     * @return int[]
     */
    private function pickOr(array $choices, $request, callable $home): array
    {
        if (count($choices) < 2) {
            return $choices;
        }

        $picked = Query::text($request, 'corporation');
        if ($picked === Access::ALL_CORPORATIONS) {
            return $choices;
        }
        if (in_array((int) $picked, $choices, true)) {
            return [(int) $picked];
        }

        return [$home() ?? $choices[0]];
    }

    /**
     * What a link sends to stay on a pick. Nothing at all with nothing to pick
     * between, so a server tracking one corporation keeps its short addresses.
     *
     * @param int[] $corporations what the page is showing
     * @param int[] $choices
     */
    protected function corporationParam(array $corporations, array $choices): ?string
    {
        if (count($choices) < 2) {
            return null;
        }

        return count($corporations) === 1 ? (string) $corporations[0] : Access::ALL_CORPORATIONS;
    }

    /**
     * The corporations a member's own board covers, and the ones they can
     * pick between: the tracked corporations their characters are in now, or
     * every tracked corporation when they are in none.
     *
     * @return array{0: int[], 1: int[]} the board's corporations, then the choices
     */
    protected function memberCorporations($request, Players $players, Settings $settings): array
    {
        $choices = Access::trackedCorporationsOf() ?: $settings->configuredCorporations();

        return [$this->pickFrom($choices, $request, $players), $choices];
    }

    /**
     * Which of these corporations a player's own figures can be read for: the
     * ones they are in now and the ones they have ratted for, so someone who
     * moved on still has what they did before.
     *
     * @param int[] $characters
     * @param int[] $corporations
     * @return int[]
     */
    protected function corporationsOfPlayer(array $characters, array $corporations): array
    {
        if ($characters === [] || $corporations === []) {
            return [];
        }

        $theirs = array_merge(
            DB::table('character_affiliations')->whereIn('character_id', $characters)->pluck('corporation_id')->all(),
            DB::table(Summaries::DAILY)->whereIn('character_id', $characters)->whereIn('corporation_id', $corporations)->distinct()->pluck('corporation_id')->all()
        );

        return array_values(array_intersect($corporations, array_map('intval', $theirs)));
    }

    /**
     * The corporations My Ratting and the viewer's own lists show, and the
     * ones they can pick between.
     *
     * @return array{0: int[], 1: int[]} what to show, then the choices
     */
    protected function ownCorporations($request, Players $players, Settings $settings): array
    {
        $choices = $this->corporationsOfPlayer($this->viewerCharacters($players), $settings->configuredCorporations());

        return [$this->pickFrom($choices, $request, $players), $choices];
    }

    /**
     * The corporations a director's page on one player shows, and the ones to
     * pick between: those the director may read that the player belongs to.
     * With nothing sent it opens on the player's own, as they see it themselves.
     *
     * @param int[] $characters
     * @param int[] $readable
     * @return array{0: int[], 1: int[]} what to show, then the choices
     */
    protected function ratterCorporations($request, array $characters, int $main, array $readable): array
    {
        $choices = $this->corporationsOfPlayer($characters, $readable) ?: $readable;

        return [$this->pickOr($choices, $request, fn () => $this->homeCorporation($characters, $main, $choices)), $choices];
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
