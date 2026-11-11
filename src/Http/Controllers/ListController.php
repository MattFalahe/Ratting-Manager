<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Npc\NpcRegistry;
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
    private const BY_PERIOD = ['sessions', 'systems', 'spawns'];

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
        [$corporations, $choices] = $this->ownCorporations($request, $this->players, $this->settings);
        $scope = new Scope($corporations, in_array($picked, $characters, true) ? [$picked] : $characters);
        $this->players->load($characters);

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::dashboard.my_ratting'),
            'back' => route('ratting-manager.dashboard', array_filter([
                'period' => Period::fromRequest($request)->key,
                'character' => $picked ?: null,
                'corporation' => $this->corporationParam($corporations, $choices),
            ])),
            'backLabel' => trans('ratting-manager::lists.back_my_ratting'),
            'characterOptions' => collect($characters)->mapWithKeys(fn ($id) => [$id => $this->players->name($id)])->sort()->all(),
            'pickedCharacter' => in_array($picked, $characters, true) ? $picked : null,
            'corporations' => $this->corporationNames($choices),
            'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
        ]);
    }

    /** One player's list, for a director looking them up. */
    public function ratter(Request $request, int $characterId, string $list)
    {
        abort_unless(in_array($list, self::PERSONAL, true), 404);

        $readable = $this->readableCorporations($this->settings);
        $characters = $this->players->charactersOfPlayer($characterId);
        $this->abortUnlessReadable($characters, $readable);

        $main = $this->players->mainOf($characterId);
        [$corporations, $choices] = $this->ratterCorporations($request, $characters, $main, $readable);
        $scope = new Scope($corporations, $characters);

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::ratter.title', ['name' => $this->players->name($main)]),
            'back' => route('ratting-manager.ratters.show', array_filter([
                'characterId' => $characterId,
                'period' => Period::fromRequest($request)->key,
                'corporation' => $this->corporationParam($corporations, $choices),
            ])),
            'backLabel' => trans('ratting-manager::lists.back_ratter'),
            'corporations' => $this->corporationNames($choices),
            'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
        ]);
    }

    /** The corporation's list, for any corporation the viewer may read or all of them. */
    public function corporation(Request $request, string $list, RattingMetrics $metrics)
    {
        abort_unless(in_array($list, self::CORPORATION, true), 404);

        $readable = $this->readableCorporations($this->settings);
        $corporations = $this->pickFrom($readable, $request, $this->players);
        $scope = new Scope($corporations);
        $param = $this->corporationParam($corporations, $readable);

        $rows = match ($list) {
            'untracked' => $metrics->untracked($corporations),
            'gone-quiet' => $metrics->goneQuiet($corporations),
            default => null,
        };

        return $this->page($request, $list, $scope, [
            'heading' => trans('ratting-manager::dashboard.corporation_ratting'),
            'back' => route('ratting-manager.dashboard', array_filter(['period' => Period::fromRequest($request)->key, 'corporation' => $param])) . '#rm-corporation',
            'backLabel' => trans('ratting-manager::lists.back_corporation'),
            'corporations' => $this->corporationNames(),
            'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
            'corporation' => true,
            'ratterQuery' => array_filter(['corporation' => $param]),
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
            'spawns' => $this->charts->spawns($scope, $period, self::MAX_ROWS),
        };

        return view('ratting-manager::lists.index', array_merge($view, [
            'list' => $list,
            'rows' => $rows,
            'period' => $period,
            'byPeriod' => in_array($list, self::BY_PERIOD, true),
            'title' => trans('ratting-manager::lists.title_' . str_replace('-', '_', $list)),
            'corporation' => $corporation,
            'columnFilters' => $this->columnFilters($list, $rows, $corporation),
        ]));
    }

    /**
     * Dropdowns that narrow the table to rows sharing a value, built from the
     * rows themselves so every choice finds something. Each row carries the
     * same value as data-filter-{key}. A filter with one value to offer
     * would narrow nothing and is left out.
     *
     * @return array<string, array{label: string, any: string, options: array<string, string>}>
     */
    private function columnFilters(string $list, array $rows, bool $corporation): array
    {
        $text = fn (string $field) => fn (array $row) => [(string) $row[$field] => (string) $row[$field]];

        $fields = match ($list) {
            'spawns' => array_filter([
                'npc' => $text('npc'),
                'faction' => fn (array $row) => [$row['faction'] => NpcRegistry::FACTIONS[$row['faction']] ?? ucfirst($row['faction'])],
                'rank' => fn (array $row) => [$row['rank'] => trans('ratting-manager::common.rank_' . $row['rank'])],
                'size' => fn (array $row) => [$row['size'] => trans('ratting-manager::common.size_' . $row['size'])],
                // A player is a main with every alt; on one player's own list
                // their characters are the only split worth having.
                'player' => $corporation ? $text('player') : null,
                'character' => $text('character'),
                'system' => $text('system'),
            ]),
            'sessions' => ['character' => $text('character'), 'system' => $text('system')],
            'untracked' => [
                'corporation' => fn (array $row) => [(string) ($row['corporation'] ?? '?') => $row['corporation'] ?? trans('ratting-manager::dashboard.corporation_unknown')],
                'on_seat' => fn (array $row) => $row['on_seat']
                    ? ['yes' => trans('ratting-manager::dashboard.on_seat')]
                    : ['no' => trans('ratting-manager::dashboard.not_on_seat')],
            ],
            default => [],
        };

        $filters = [];
        foreach ($fields as $key => $option) {
            $options = [];
            foreach ($rows as $row) {
                $options += $option($row);
            }
            if (count($options) < 2) {
                continue;
            }
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
            $filters[$key] = [
                'label' => trans('ratting-manager::lists.filters.' . $key),
                'any' => trans('ratting-manager::lists.any.' . $key),
                'options' => array_map('strval', $options),
            ];
        }

        return $filters;
    }
}
