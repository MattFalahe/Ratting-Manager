<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\ChartData;
use RattingManager\Services\Ratting\Insights;
use RattingManager\Services\Ratting\MonthlyBreakdown;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use RattingManager\Services\Ratting\WalletGaps;
use RattingManager\Support\Access;

class DashboardController extends Controller
{
    use ResolvesViewer;

    /** How many of the most-ratted systems the ISK per hour charts compare. */
    public const EFFICIENCY_SYSTEMS_PERSONAL = 10;
    public const EFFICIENCY_SYSTEMS_CORPORATION = 15;

    private RattingMetrics $metrics;
    private ChartData $charts;
    private Players $players;
    private Settings $settings;
    private Insights $insights;
    private MonthlyBreakdown $monthly;

    public function __construct(
        RattingMetrics $metrics,
        ChartData $charts,
        Players $players,
        Settings $settings,
        Insights $insights,
        MonthlyBreakdown $monthly
    ) {
        $this->metrics = $metrics;
        $this->charts = $charts;
        $this->players = $players;
        $this->settings = $settings;
        $this->insights = $insights;
        $this->monthly = $monthly;
    }

    /**
     * My Ratting for everyone; directors also get the Corporation tab, which
     * loads on first open so a member-sized page stays quick.
     */
    public function index(Request $request)
    {
        $period = Period::fromRequest($request);
        $configured = $this->settings->configuredCorporations();

        if ($configured === []) {
            return view('ratting-manager::dashboard.index', [
                'period' => $period,
                'notConfigured' => true,
                'isDirector' => Access::seesCorporations(),
                'isAdmin' => Access::isAdmin(),
            ]);
        }

        $seesCorporations = Access::seesCorporations();
        $characters = $this->viewerCharacters($this->players);
        $picked = (int) $request->query('character', 0);
        $scopeCharacters = in_array($picked, $characters, true) ? [$picked] : $characters;

        $personal = $this->personal(new Scope($configured, $scopeCharacters), $period, $request->query('month'));

        $board = $this->metrics->hoursBoard(
            new Scope($this->viewerCorporations($this->players, $this->settings)),
            $period,
            $this->viewerMain($this->players)
        );

        $this->players->load($characters);

        return view('ratting-manager::dashboard.index', array_merge($personal, [
            'period' => $period,
            'notConfigured' => false,
            'isDirector' => $seesCorporations,
            'isAdmin' => Access::isAdmin(),
            'ownRank' => $board['own_rank'],
            'rankedPlayers' => $board['players'],
            'characterOptions' => collect($characters)->mapWithKeys(fn ($id) => [$id => $this->players->name($id)])->sort()->all(),
            'pickedCharacter' => in_array($picked, $characters, true) ? $picked : null,
            'corporations' => $seesCorporations ? $this->corporationNames() : [],
            'listUrl' => fn (string $list) => route('ratting-manager.lists.personal', array_filter([
                'list' => $list,
                'period' => $period->key,
                'character' => in_array($picked, $characters, true) ? $picked : null,
            ])),
        ]));
    }

    /**
     * The Corporation tab, rendered here and handed to the page with the
     * chart data beside it.
     */
    public function corporationTab(Request $request)
    {
        $period = Period::fromRequest($request);
        $corporations = $this->pickedCorporations($request, $this->settings);
        $scope = new Scope($corporations);

        $board = $this->metrics->leaderboard($scope, $period);

        $data = [
            'period' => $period,
            'totals' => $board['totals'],
            'leaderboard' => $this->players->markStanding($board['rows'], $corporations),
            'median' => $board['median_isk_per_hour'],
            'systems' => $this->charts->systems($scope, $period),
            'spaceCounts' => $this->charts->spaceCounts($scope, $period),
            'goneQuiet' => $this->metrics->goneQuiet($corporations),
            'untracked' => $this->metrics->untracked($corporations),
            'walletGaps' => app(WalletGaps::class)->forCorporations($corporations),
            'spawns' => $this->charts->spawns($scope, ListController::DASHBOARD_ROWS),
            'spawnTotal' => $this->charts->spawnCount($scope),
            'kills' => $this->charts->kills($scope, $period),
            'insights' => $insights = $this->insights->for($scope, $period, true),
            'heatmap' => $insights['heatmap'],
            'months' => $this->monthly->corporationMonths($scope),
            'listQuery' => $listQuery = array_filter(['period' => $period->key, 'corporation' => $request->query('corporation')]),
            'listUrl' => fn (string $list) => route('ratting-manager.lists.corporation', ['list' => $list] + $listQuery),
        ];

        $html = view('ratting-manager::dashboard.partials.corporation', $data)->render();

        return response()->json([
            'html' => $html,
            'pending' => $this->players->shownPending(),
            'charts' => [
                'monthly' => $this->charts->monthly($scope, true),
                'daily' => $this->charts->daily($scope, $period),
                'systems' => $data['systems'],
                'kills' => $data['kills'],
                'movement' => $this->charts->ratterMovement($scope),
                'concentration' => $this->charts->concentration($board['rows']),
                'weekdays' => $insights['weekdays'],
                'hours' => $insights['hours'],
                'efficiency' => array_slice($insights['systems'], 0, self::EFFICIENCY_SYSTEMS_CORPORATION),
                'months' => array_reverse($data['months']),
            ],
        ]);
    }

    /**
     * Everything My Ratting shows, for any set of characters. The ratter page
     * uses the same for one player.
     */
    public function personal(Scope $scope, Period $period, ?string $month = null): array
    {
        $collected = $this->metrics->collect($scope, $period->start, $period->end);
        $insights = $this->insights->for($scope, $period, false);
        $pickedMonth = MonthlyBreakdown::month($month);
        // One read for both: the table shows the first rows, the chart
        // plots every session of the period, whatever the row limit.
        $sessions = $this->charts->sessions($scope, $period, ListController::DASHBOARD_ROWS);

        return [
            'totals' => $collected['totals'],
            'insights' => $insights,
            'heatmap' => $insights['heatmap'],
            'sessions' => $sessions,
            'records' => $this->charts->records($scope),
            'spawns' => $this->charts->spawns($scope, ListController::DASHBOARD_ROWS),
            'spawnTotal' => $this->charts->spawnCount($scope),
            'monthBreakdown' => $this->monthly->byCharacter($scope, $pickedMonth),
            'monthMatrix' => $this->monthly->characterMatrix($scope),
            'monthChoices' => MonthlyBreakdown::monthChoices(),
            'pickedMonth' => $pickedMonth->format('Y-m'),
            'personalCharts' => [
                'monthly' => $this->charts->monthly($scope, true),
                'daily' => $this->charts->daily($scope, $period),
                'systems' => $this->charts->systems($scope, $period, 10),
                'kills' => $this->charts->kills($scope, $period),
                'characters' => $this->charts->byCharacter($scope, $period),
                'sessions' => $sessions['points'],
                'weekdays' => $insights['weekdays'],
                'hours' => $insights['hours'],
                'efficiency' => array_slice($insights['systems'], 0, self::EFFICIENCY_SYSTEMS_PERSONAL),
            ],
        ];
    }
}
