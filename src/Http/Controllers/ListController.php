<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\ChartData;
use RattingManager\Services\Ratting\Insights;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use Seat\Web\Http\Controllers\Controller;

/**
 * The whole of a list the dashboards cut short: every session, system or
 * special spawn of a player, and every system, special spawn, untracked
 * ratter or quiet player of the corporation, searchable and sortable.
 */
class ListController extends Controller
{
    use ResolvesViewer;

    /** How many rows a dashboard shows before the View all link. */
    public const DASHBOARD_ROWS = 10;

    /** Rows enough for any real list, so a page can never be asked for everything at once. */
    private const MAX_ROWS = 5000;

    private const PERSONAL = ['sessions', 'systems', 'spawns'];
    private const CORPORATION = ['systems', 'spawns', 'untracked', 'gone-quiet'];

    /** Lists whose rows depend on the period picked. */
    private const BY_PERIOD = ['sessions', 'systems'];

    private ChartData $charts;
    private Insights $insights;
    private Players $players;
    private Settings $settings;

    public function __construct(ChartData $charts, Insights $insights, Players $players, Settings $settings)
    {
        $this->charts = $charts;
        $this->insights = $insights;
        $this->players = $players;
        $this->settings = $settings;
    }

    /** The signed-in member's own list, across their characters or one of them. */
    public function personal(Request $request, string $list)
    {
        abort_unless(in_array($list, self::PERSONAL, true), 404);

        $characters = $this->viewerCharacters($this->players);
        $picked = (int) $request->query('character', 0);
        $scope = new Scope($this->settings->configuredCorporations(), in_array($picked, $characters, true) ? [$picked] : $characters);
        $this->players->load($characters);

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::dashboard.my_ratting'),
            'back' => route('ratting-manager.dashboard', array_filter(['period' => $request->query('period'), 'character' => $picked ?: null])),
            'backLabel' => trans('ratting-manager::lists.back_my_ratting'),
            'characterOptions' => collect($characters)->mapWithKeys(fn ($id) => [$id => $this->players->name($id)])->sort()->all(),
            'pickedCharacter' => in_array($picked, $characters, true) ? $picked : null,
        ]);
    }

    /** One player's list, for a director looking them up. */
    public function ratter(Request $request, int $characterId, string $list)
    {
        abort_unless(in_array($list, self::PERSONAL, true), 404);

        $corporations = $this->readableCorporations($this->settings);
        $characters = $this->players->charactersOfPlayer($characterId);
        $this->abortUnlessReadable($characters, $corporations);

        $scope = new Scope($corporations, $characters);
        $main = $this->players->mainOf($characterId);

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::ratter.title', ['name' => $this->players->name($main)]),
            'back' => route('ratting-manager.ratters.show', array_filter(['characterId' => $characterId, 'period' => $request->query('period')])),
            'backLabel' => trans('ratting-manager::lists.back_ratter'),
        ]);
    }

    /** The corporation's list, for any corporation the viewer may read or all of them. */
    public function corporation(Request $request, string $list, RattingMetrics $metrics)
    {
        abort_unless(in_array($list, self::CORPORATION, true), 404);

        $corporations = $this->pickedCorporations($request, $this->settings);
        $scope = new Scope($corporations);

        $rows = match ($list) {
            'untracked' => $metrics->untracked($corporations),
            'gone-quiet' => $metrics->goneQuiet($corporations),
            default => null,
        };

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::dashboard.corporation_ratting'),
            'back' => route('ratting-manager.dashboard', array_filter(['period' => $request->query('period')])) . '#rm-corporation',
            'backLabel' => trans('ratting-manager::lists.back_corporation'),
            'corporations' => $this->corporationNames(),
            'pickedCorporation' => count($corporations) === 1 && $request->query('corporation') ? $corporations[0] : null,
            'corporation' => true,
            'rows' => $rows,
        ]);
    }

    private function page(Request $request, string $list, Scope $scope, array $view)
    {
        $period = Period::fromRequest($request);
        $corporation = $view['corporation'] ?? false;

        $rows = $view['rows'] ?? match ($list) {
            'sessions' => $this->charts->sessions($scope, $period, self::MAX_ROWS)['list'],
            'systems' => $this->insights->for($scope, $period, $corporation)['systems'],
            'spawns' => $this->charts->spawns($scope, self::MAX_ROWS),
        };

        return view('ratting-manager::lists.index', array_merge($view, [
            'list' => $list,
            'rows' => $rows,
            'period' => $period,
            'byPeriod' => in_array($list, self::BY_PERIOD, true),
            'title' => trans('ratting-manager::lists.title_' . str_replace('-', '_', $list)),
            'corporation' => $corporation,
        ]));
    }
}
