<?php

namespace RattingManager\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use RattingManager\Http\Controllers\Concerns\ResolvesViewer;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\ChartData;
use RattingManager\Services\Ratting\MonthlyBreakdown;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use RattingManager\Support\Csv;
use Seat\Web\Http\Controllers\Controller;

/**
 * One calendar month for a corporation's books: ISK ratted, the corporation's
 * cut, what the pilots received, and everyone who ratted for it that month,
 * biggest first. Ratting counts for the corporation a character was in at the
 * time, so a past month still lists the people who have left since, marked as
 * ex-members with the time they went.
 */
class ReportController extends Controller
{
    use ResolvesViewer;

    /** The pages show a year at most, this month and the eleven before it. */
    private const MONTHS = 12;

    private const TOP_SYSTEMS = 10;

    public function index(Request $request, Settings $settings, RattingMetrics $metrics, Players $players, ChartData $charts)
    {
        if ($settings->configuredCorporations() === []) {
            return view('ratting-manager::report.index', ['notConfigured' => true]);
        }

        return view('ratting-manager::report.index', $this->figures($request, $settings, $metrics, $players, $charts) + [
            'notConfigured' => false,
            'monthChoices' => MonthlyBreakdown::monthChoices(self::MONTHS),
            'corporations' => $this->corporationNames(),
        ]);
    }

    /** The same members as a CSV, with a total line. */
    public function csv(Request $request, Settings $settings, RattingMetrics $metrics, Players $players, ChartData $charts)
    {
        abort_if($settings->configuredCorporations() === [], 404);

        $f = $this->figures($request, $settings, $metrics, $players, $charts);

        $lines = [];
        foreach ($f['rows'] as $i => $r) {
            $lines[] = [
                $i + 1,
                $r['name'],
                count($r['characters']),
                (float) $r['ratted'],
                (float) $r['corp_tax'],
                (float) $r['income'],
                (float) $r['bounty'],
                (float) $r['ess'],
                round($r['minutes'] / 60, 2),
                (float) $r['isk_per_hour'],
                (int) $r['rats'],
                (int) $r['active_days'],
                $r['left'] ? ($r['left_at'] ? substr($r['left_at'], 0, 16) : trans('ratting-manager::ratter.yes')) : '',
                $r['seat_auth'] ? trans('ratting-manager::ratter.yes') : '',
            ];
        }

        $t = $f['totals'];
        $lines[] = ['', trans('ratting-manager::report.total'), (int) $t['characters'], (float) $t['ratted'], (float) $t['corp_tax'], (float) $t['income'], (float) $t['bounty'], (float) $t['ess'], round($t['minutes'] / 60, 2), (float) $t['isk_per_hour'], (int) $t['rats'], (int) $t['active_days'], '', ''];

        return Csv::download(
            'ratting-report-' . $f['month']->format('Y-m') . '.csv',
            array_values(trans('ratting-manager::report.csv')),
            $lines
        );
    }

    private function figures(Request $request, Settings $settings, RattingMetrics $metrics, Players $players, ChartData $charts): array
    {
        $month = self::month($request->query('month'));
        $period = Period::month($month);
        $corporations = $this->pickedCorporations($request, $settings);
        $scope = new Scope($corporations);

        $board = $metrics->leaderboard($scope, $period);
        $rows = $players->markStanding($board['rows'], $corporations);
        usort($rows, fn ($a, $b) => $b['ratted'] <=> $a['ratted']);

        // A month before the ratting on file started would show as a jump from almost nothing.
        $before = $metrics->covers($scope, $period->previousStart, $period->previousEnd)
            ? $metrics->collect($scope, $period->previousStart, $period->previousEnd)['totals']
            : ['ratted' => 0];
        $totals = $board['totals'];

        return [
            'month' => $month,
            'pickedMonth' => $month->format('Y-m'),
            'corporationIds' => $corporations,
            'pickedCorporation' => count($corporations) === 1 ? $corporations[0] : null,
            'rows' => $rows,
            'totals' => $totals,
            'median' => $board['median_isk_per_hour'],
            'taxShare' => $totals['ratted'] > 0 ? round($totals['corp_tax'] / $totals['ratted'] * 100, 1) : null,
            'change' => $before['ratted'] > 0 ? round(($totals['ratted'] - $before['ratted']) / $before['ratted'] * 100) : null,
            'exMembers' => count(array_filter($rows, fn ($r) => $r['left'])),
            'systems' => $charts->systems($scope, $period, self::TOP_SYSTEMS),
            'spaceCounts' => $charts->spaceCounts($scope, $period),
        ];
    }

    /** The month asked for, or the last full one. */
    private static function month(?string $value): Carbon
    {
        return array_key_exists((string) $value, MonthlyBreakdown::monthChoices(self::MONTHS))
            ? Carbon::createFromFormat('Y-m-d H:i:s', $value . '-01 00:00:00', 'UTC')
            : now('UTC')->startOfMonth()->subMonthNoOverflow();
    }
}
