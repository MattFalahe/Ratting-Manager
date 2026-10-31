<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use RattingManager\Support\Access;
use Seat\Web\Http\Controllers\Controller;

class LeaderboardController extends Controller
{
    use ResolvesViewer;

    /**
     * Members see rank and hours for their own corporation, ranked by hours,
     * and nobody's ISK. Directors see every column, for the corporations they
     * may read.
     */
    public function index(Request $request, RattingMetrics $metrics, Players $players, Settings $settings)
    {
        $period = Period::fromRequest($request);
        $isDirector = Access::seesCorporations();
        $size = max(5, (int) $settings->get('leaderboard.size'));

        if ($settings->configuredCorporations() === []) {
            return view('ratting-manager::leaderboard.index', [
                'period' => $period,
                'notConfigured' => true,
                'isDirector' => $isDirector,
                'isAdmin' => Access::isAdmin(),
            ]);
        }

        $main = $this->viewerMain($players);

        if ($isDirector) {
            $corporations = $this->pickedCorporations($request, $settings);
            $board = $metrics->leaderboard(new Scope($corporations), $period);

            return view('ratting-manager::leaderboard.index', [
                'period' => $period,
                'notConfigured' => false,
                'isDirector' => true,
                'isAdmin' => Access::isAdmin(),
                'rows' => $players->markStanding($board['rows'], $corporations),
                'median' => $board['median_isk_per_hour'],
                'totals' => $board['totals'],
                'ownMain' => $main,
                'corporations' => $this->corporationNames(),
                'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
            ]);
        }

        $corporations = $this->viewerCorporations($players, $settings);
        $board = $metrics->hoursBoard(new Scope($corporations), $period, $main);

        // The top of the board, and the member's own row when it is further down.
        $rows = array_slice($board['rows'], 0, $size);
        if ($board['own_rank'] !== null && $board['own_rank'] > $size) {
            $rows[] = $board['rows'][$board['own_rank'] - 1];
        }

        return view('ratting-manager::leaderboard.index', [
            'period' => $period,
            'notConfigured' => false,
            'isDirector' => false,
            'isAdmin' => false,
            'rows' => $players->markStanding($rows, $corporations),
            'ownMain' => $main,
            'ownRank' => $board['own_rank'],
            'playerCount' => $board['players'],
        ]);
    }

    /** The directors' leaderboard as a CSV, for the same corporation and period, ranked as the page ranks it. */
    public function csv(Request $request, RattingMetrics $metrics, Players $players, Settings $settings)
    {
        abort_if($settings->configuredCorporations() === [], 404);

        $period = Period::fromRequest($request);
        $corporations = $this->pickedCorporations($request, $settings);
        $board = $metrics->leaderboard(new Scope($corporations), $period);
        $rows = $players->markStanding($board['rows'], $corporations);

        $percent = fn ($value) => $value === null ? null : (int) $value;

        $lines = [];
        foreach ($rows as $i => $r) {
            $lines[] = [
                $i + 1,
                $r['name'],
                count($r['characters']),
                (float) $r['ratted'],
                (float) $r['income'],
                (float) $r['bounty'],
                (float) $r['ess'],
                (float) $r['estimated'],
                (float) $r['corp_tax'],
                round($r['minutes'] / 60, 2),
                (float) $r['isk_per_hour'],
                $percent($r['efficiency']),
                (int) $r['rats'],
                (int) $r['active_days'],
                $percent($r['change']),
                $percent($r['coverage']),
                $r['left'] ? ($r['left_at'] ? substr($r['left_at'], 0, 16) : trans('ratting-manager::ratter.yes')) : '',
                $r['seat_auth'] ? trans('ratting-manager::ratter.yes') : '',
            ];
        }

        return \RattingManager\Support\Csv::download(
            'ratting-leaderboard-' . $period->start->format('Y-m-d') . '-to-' . $period->end->format('Y-m-d') . '.csv',
            array_values(trans('ratting-manager::leaderboard.csv')),
            $lines
        );
    }
}
