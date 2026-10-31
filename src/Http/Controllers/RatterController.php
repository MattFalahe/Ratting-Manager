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
use Seat\Web\Http\Controllers\Controller;

class RatterController extends Controller
{
    use ResolvesViewer;

    /** Every player who ratted in the last 90 days, to search and open. */
    public function index(RattingMetrics $metrics, Players $players, Settings $settings)
    {
        $corporations = $this->readableCorporations($settings);
        $rows = $corporations === []
            ? []
            : array_values($metrics->collect(new Scope($corporations), now()->subDays(89)->startOfDay(), now())['players']);

        usort($rows, fn ($a, $b) => strcmp((string) $b['last_tick'], (string) $a['last_tick']));

        return view('ratting-manager::ratter.index', [
            'rows' => $players->markStanding($rows, $corporations),
            'notConfigured' => $corporations === [],
        ]);
    }

    /** One player's ratting, as they see it on My Ratting, plus where each character's figures come from. */
    public function show(Request $request, int $characterId, Players $players, Settings $settings, DashboardController $dashboard, RattingMetrics $metrics, WalletGaps $gaps)
    {
        $corporations = $this->readableCorporations($settings);
        $period = Period::fromRequest($request);
        $characters = $players->charactersOfPlayer($characterId);
        $this->abortUnlessReadable($characters, $corporations);
        $main = $players->mainOf($characterId);

        $personal = $dashboard->personal(new Scope($corporations, $characters), $period, $request->query('month'));

        // The player's place by hours, on the board they see on My Ratting,
        // as long as that is a board this viewer may read.
        $boardCorporations = array_values(array_intersect($this->corporationsOf($characters, $settings), $corporations)) ?: $corporations;
        $board = $metrics->hoursBoard(new Scope($boardCorporations), $period, $main);

        $kinds = Ticks::kindsOf($characters, now()->subDays(90), $corporations);
        $lost = $gaps->forCharacters($characters);

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
                'corporation' => $players->currentCorporationName($id),
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
            'listUrl' => fn (string $list) => route('ratting-manager.ratters.list', ['characterId' => $characterId, 'list' => $list, 'period' => $period->key]),
        ]));
    }
}
