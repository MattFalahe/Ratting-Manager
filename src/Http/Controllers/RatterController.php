<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use RattingManager\Services\Ratting\Summaries;
use RattingManager\Services\Ratting\Ticks;
use RattingManager\Services\Ratting\WalletGaps;
use RattingManager\Support\Query;
use Seat\Web\Http\Controllers\Controller;

class RatterController extends Controller
{
    use ResolvesViewer;

    /** How far back the Ratter Lookup reaches. */
    public const LOOKUP_DAYS = 90;

    /** Every player who ratted for the corporation picked in the last 90 days, to search and open. */
    public function index(Request $request, RattingMetrics $metrics, Players $players, Settings $settings)
    {
        $readable = $this->readableCorporations($settings);
        $corporations = $this->pickFrom($readable, $request, $players);
        $rows = $corporations === []
            ? []
            : array_values($metrics->collect(new Scope($corporations), now()->subDays(self::LOOKUP_DAYS - 1)->startOfDay(), now())['players']);

        usort($rows, fn ($a, $b) => strcmp((string) $b['last_tick'], (string) $a['last_tick']));

        return view('ratting-manager::ratter.index', [
            'period' => Period::fromRequest($request),
            'rows' => $players->markStanding($rows, $corporations),
            'notConfigured' => $corporations === [],
            'corporations' => $this->corporationNames($readable),
            'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
            'ratterQuery' => array_filter(['corporation' => $this->corporationParam($corporations, $readable)]),
        ]);
    }

    /** One player's ratting, as they see it on My Ratting, plus where each character's figures come from. */
    public function show(Request $request, int $characterId, Players $players, Settings $settings, DashboardController $dashboard, RattingMetrics $metrics, WalletGaps $gaps)
    {
        $readable = $this->readableCorporations($settings);
        $period = Period::fromRequest($request);
        $characters = $players->charactersOfPlayer($characterId);
        $this->abortUnlessReadable($characters, $readable);
        $main = $players->mainOf($characterId);

        [$corporations, $choices] = $this->ratterCorporations($request, $characters, $main, $readable);
        $param = $this->corporationParam($corporations, $choices);

        $personal = $dashboard->personal(new Scope($corporations, $characters), $period, Query::text($request, 'month'));

        // The player's place by hours, on the board of what this page shows.
        $board = $metrics->hoursBoard(new Scope($corporations), $period, $main);

        $kinds = Ticks::kindsOf($characters, now()->subDays(90), $corporations);
        $lost = $gaps->forCharacters($characters);
        $corporationNow = $players->currentCorporationNames($characters);

        $sources = [];
        foreach ($characters as $id) {
            $counts = ($kinds[$id] ?? []) + ['paired' => 0, 'character' => 0, 'corporation' => 0];
            $seenByCorp = $counts['paired'] + $counts['corporation'];
            $sources[] = [
                'character_id' => $id,
                'name' => $players->name($id),
                'wallet_token' => $players->hasWalletToken($id),
                'wallet_gap' => $lost[$id] ?? null,
                'seat_auth' => $players->characterAuth($id),
                'counts' => $counts,
                'coverage' => $seenByCorp > 0 ? round($counts['paired'] / $seenByCorp * 100) : null,
                'corporation' => $corporationNow[$id] ?? null,
            ];
        }

        $standing = $players->markStanding([['main_id' => $main, 'characters' => $characters]], $corporations)[0];

        return view('ratting-manager::ratter.show', array_merge($personal, [
            'period' => $period,
            'ownRank' => $board['own_rank'],
            'rankedPlayers' => $board['players'],
            'standing' => $standing,
            // What landed in the wallet from them over the months the pages show.
            'corporationCut' => $standing['left']
                ? (float) Summaries::months(new Scope($corporations, $characters), now()->subMonthsNoOverflow(11)->format('Y-m'))->sum(DB::raw('bounty_corp_tax + ess_corp_tax'))
                : 0.0,
            'mainId' => $main,
            'mainName' => $players->name($main),
            'sources' => $sources,
            'characterId' => $characterId,
            'playerCorporations' => $this->corporationNames($choices),
            'pickedPlayerCorporation' => count($corporations) === 1 ? $corporations[0] : null,
            'playerCorporationParam' => $param,
            'lookupUrl' => route('ratting-manager.ratters.index', array_filter(['corporation' => Query::text($request, 'corporation')])),
            'listUrl' => fn (string $list) => route('ratting-manager.ratters.list', array_filter(['characterId' => $characterId, 'list' => $list, 'period' => $period->key, 'corporation' => $param])),
        ]));
    }
}
