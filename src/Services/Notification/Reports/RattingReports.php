<?php

namespace RattingManager\Services\Notification\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Notification\AlertState;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Notification\Notifier;
use RattingManager\Services\Ratting\Insights;
use RattingManager\Services\Ratting\Period;
use RattingManager\Services\Ratting\RattingMetrics;
use RattingManager\Services\Ratting\Scope;
use RattingManager\Support\Format;

/**
 * The weekly and monthly posts, one set per tracked corporation:
 *
 * - the Director Report: what the players earned, what landed in the
 *   corporation's wallet, and players ranked by income, hours, ISK per hour
 *   and corporation tax paid
 * - the Rankings: players ranked by hours only, with no ISK, for a
 *   members' channel
 *
 * Players are ranked as a main with all their alts. Each post is sent once
 * per period. A post nothing is bound to yet is tried again on the next
 * run, so a webhook bound a day late still gets the week just ended.
 */
class RattingReports
{
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    private Notifier $notifier;
    private AlertState $state;
    private Settings $settings;
    private RattingMetrics $metrics;
    private Insights $insights;

    public function __construct(Notifier $notifier, AlertState $state, Settings $settings, RattingMetrics $metrics, Insights $insights)
    {
        $this->notifier = $notifier;
        $this->state = $state;
        $this->settings = $settings;
        $this->metrics = $metrics;
        $this->insights = $insights;
    }

    /** The last full week or month before the given moment. */
    public static function lastPeriod(string $cadence, ?Carbon $now = null): Period
    {
        $now = ($now ?? Carbon::now('UTC'))->copy();

        return $cadence === self::MONTHLY
            ? Period::month($now->subMonthNoOverflow())
            : Period::week($now->subWeek());
    }

    public static function periodKey(string $cadence, Period $period): string
    {
        return $cadence === self::MONTHLY ? $period->start->format('Y-m') : $period->start->format('o-\WW');
    }

    public static function periodLabel(string $cadence, Period $period): string
    {
        return $cadence === self::MONTHLY
            ? $period->start->format('F Y')
            : trans('ratting-manager::notifications.week_label', [
                'week' => $period->start->isoWeek(),
                'from' => $period->start->format('j M'),
                'to' => $period->end->format('j M Y'),
            ]);
    }

    /**
     * Sends whatever is due for the cadence. With $force the posts go out
     * again even if they were sent already.
     *
     * @return array<int, array{type: string, corporation: int, period: string, bindings: int, delivered: int}>
     */
    public function send(string $cadence, bool $force = false, ?Carbon $now = null): array
    {
        $period = self::lastPeriod($cadence, $now);
        $periodKey = self::periodKey($cadence, $period);
        $sent = [];

        foreach ($this->settings->configuredCorporations() as $corporationId) {
            foreach (['director_report_' . $cadence, 'rankings_' . $cadence] as $type) {
                $key = $type . ':' . $corporationId . ':' . $periodKey;
                if (!$this->notifier->isEnabled($type) || (!$force && $this->state->isOpen($key))) {
                    continue;
                }
                if (!$this->notifier->hasRecipients($type, $corporationId)) {
                    continue;
                }

                try {
                    $message = str_starts_with($type, 'director_report')
                        ? $this->directorReport($corporationId, $period, $cadence)
                        : $this->rankings($corporationId, $period, $cadence);

                    $result = $this->notifier->send($type, $corporationId, $message);
                    if ($result['delivered'] > 0) {
                        $this->state->opened($key);
                    }

                    $sent[] = ['type' => $type, 'corporation' => $corporationId, 'period' => $periodKey] + $result;
                } catch (\Throwable $e) {
                    // One post failing must not stop the others. It was not
                    // marked sent, so the next daily run tries it again.
                    report($e);
                }
            }
        }

        return $sent;
    }

    public function directorReport(int $corporationId, Period $period, string $cadence): Message
    {
        $scope = new Scope([$corporationId]);
        $board = $this->metrics->leaderboard($scope, $period);
        $previous = $this->metrics->collect($scope, $period->previousStart, $period->previousEnd)['totals'];
        $systems = array_slice($this->insights->for($scope, $period, true)['systems'], 0, 3);

        return $this->buildDirectorReport($this->corporationName($corporationId), self::periodLabel($cadence, $period), $board['totals'], $previous, $board['median_isk_per_hour'], $board['rows'], $systems, $cadence);
    }

    public function rankings(int $corporationId, Period $period, string $cadence): Message
    {
        $rows = $this->metrics->leaderboard(new Scope([$corporationId]), $period)['rows'];

        return $this->buildRankings($this->corporationName($corporationId), self::periodLabel($cadence, $period), $rows, $cadence);
    }

    /** The two posts from made-up figures, for the Test buttons. */
    public function sample(string $type): Message
    {
        $cadence = str_ends_with($type, self::MONTHLY) ? self::MONTHLY : self::WEEKLY;
        $label = self::periodLabel($cadence, self::lastPeriod($cadence));
        $rows = [
            ['name' => 'Purpleshadez', 'income' => 2_171_400_000.0, 'corp_tax' => 383_200_000.0, 'minutes' => 1000, 'isk_per_hour' => 130_280_000.0],
            ['name' => 'Solo Ratter', 'income' => 1_925_900_000.0, 'corp_tax' => 339_900_000.0, 'minutes' => 800, 'isk_per_hour' => 144_440_000.0],
            ['name' => 'Rykel Fairchilde', 'income' => 190_000_000.0, 'corp_tax' => 33_500_000.0, 'minutes' => 1240, 'isk_per_hour' => 9_190_000.0],
        ];

        if (str_starts_with($type, 'rankings')) {
            return $this->buildRankings('Test Corp', $label, $rows, $cadence);
        }

        $totals = ['income' => 4_287_300_000.0, 'bounty' => 2_489_700_000.0, 'ess' => 1_797_600_000.0, 'estimated' => 2_222_100_000.0, 'corp_tax' => 756_600_000.0, 'players' => 3, 'characters' => 4, 'minutes' => 3040, 'rats' => 1140];
        $previous = ['income' => 3_900_000_000.0, 'corp_tax' => 690_000_000.0];
        $systems = [['name' => '0-VG7A', 'minutes' => 1000], ['name' => '3AE-CP', 'minutes' => 800], ['name' => 'Q-3HS5', 'minutes' => 800]];

        return $this->buildDirectorReport('Test Corp', $label, $totals, $previous, 130_280_000.0, $rows, $systems, $cadence);
    }

    private function buildDirectorReport(string $corporation, string $label, array $totals, array $previous, float $median, array $rows, array $systems, string $cadence): Message
    {
        $t = fn ($key, $replace = []) => trans('ratting-manager::notifications.' . $key, $replace);
        $type = 'director_report_' . $cadence;
        $message = Message::forType($type, $t('report_title', ['corporation' => $corporation, 'period' => $label]))
            ->url($this->dashboardUrl());

        if (($totals['players'] ?? 0) === 0) {
            return $message->description($t('report_empty'));
        }

        $earned = $t('earned_value', ['income' => Format::isk($totals['income']), 'bounty' => Format::isk($totals['bounty']), 'ess' => Format::isk($totals['ess'])]);
        if (($totals['estimated'] ?? 0) > 0) {
            $earned .= "\n" . $t('earned_worked_out', ['amount' => Format::isk($totals['estimated'])]);
        }
        $paidOut = $totals['income'] + $totals['corp_tax'];

        $message
            ->field($t('earned'), $earned)
            ->field($t('tax_landed'), $t('tax_value', [
                'tax' => Format::isk($totals['corp_tax']),
                'rate' => $paidOut > 0 ? number_format($totals['corp_tax'] / $paidOut * 100, 1) . '%' : '-',
            ]))
            ->field($t('change'), ($previous['income'] ?? 0) > 0
                ? $t('change_value', ['income' => self::change($totals['income'], $previous['income']), 'tax' => self::change($totals['corp_tax'], $previous['corp_tax'] ?? 0)])
                : $t('change_none'))
            ->field($t('players'), $t('players_value', ['players' => $totals['players'], 'characters' => $totals['characters']]))
            ->field($t('hours'), Format::hours((int) $totals['minutes']))
            ->field($t('median'), Format::isk($median) . "\n" . $t('median_hint'))
            ->field($t('rats'), number_format($totals['rats']))
            ->field($t('top_systems'), implode("\n", array_map(fn ($s) => $s['name'] . ', ' . Format::hours($s['minutes']), $systems)), true);

        $size = max(1, (int) $this->settings->get('notifications.rankings_size'));

        $byIncome = self::ranked($rows, 'income', fn ($r) => $r['income'] > 0);
        $message->field($t('rank_income'), self::rankLines($byIncome, $size, fn ($r, $rank) => $t('rank_income_line', [
            'rank' => $rank, 'name' => $r['name'], 'isk' => Format::isk($r['income']), 'tax' => Format::isk($r['corp_tax']),
        ])), false);

        $byHours = self::ranked($rows, 'minutes', fn ($r) => $r['minutes'] > 0);
        $message->field($t('rank_hours'), self::rankLines($byHours, $size, fn ($r, $rank) => $t('rank_hours_line', [
            'rank' => $rank, 'name' => $r['name'], 'time' => Format::hours((int) $r['minutes']),
        ])), false);

        $byRate = self::ranked($rows, 'isk_per_hour', fn ($r) => $r['minutes'] >= 60);
        $message->field($t('rank_rate'), self::rankLines($byRate, $size, fn ($r, $rank) => $t('rank_rate_line', [
            'rank' => $rank, 'name' => $r['name'], 'isk' => Format::isk($r['isk_per_hour']),
        ])), false);

        $byTax = self::ranked($rows, 'corp_tax', fn ($r) => $r['corp_tax'] > 0);
        $message->field($t('rank_tax'), self::rankLines($byTax, $size, fn ($r, $rank) => $t('rank_tax_line', [
            'rank' => $rank, 'name' => $r['name'], 'isk' => Format::isk($r['corp_tax']),
        ])), false);

        return $message;
    }

    private function buildRankings(string $corporation, string $label, array $rows, string $cadence): Message
    {
        $t = fn ($key, $replace = []) => trans('ratting-manager::notifications.' . $key, $replace);
        $byHours = self::ranked($rows, 'minutes', fn ($r) => $r['minutes'] > 0);
        $size = max(1, (int) $this->settings->get('notifications.rankings_size'));

        $message = Message::forType('rankings_' . $cadence, $t('rankings_title', ['corporation' => $corporation, 'period' => $label]))
            ->url($this->dashboardUrl());

        if ($byHours === []) {
            return $message->description($t('report_empty'));
        }

        return $message
            ->description($t('rankings_body', [
                'players' => count($byHours),
                'hours' => Format::hours((int) array_sum(array_column($byHours, 'minutes'))),
            ]))
            ->field($t('rank_hours'), self::rankLines($byHours, $size, fn ($r, $rank) => $t('rank_hours_line', [
                'rank' => $rank, 'name' => $r['name'], 'time' => Format::hours((int) $r['minutes']),
            ])), false);
    }

    private static function ranked(array $rows, string $field, callable $keep): array
    {
        $rows = array_values(array_filter($rows, $keep));
        usort($rows, fn ($a, $b) => $b[$field] <=> $a[$field]);

        return $rows;
    }

    /** Ranked lines, as many as fit in one embed field. */
    private static function rankLines(array $rows, int $size, callable $line): string
    {
        if ($rows === []) {
            return trans('ratting-manager::notifications.nobody');
        }

        $medals = ["\u{1F947}", "\u{1F948}", "\u{1F949}"];
        $lines = [];
        $length = 0;
        foreach (array_slice($rows, 0, $size) as $i => $row) {
            $text = $line($row, $medals[$i] ?? ($i + 1) . '.');
            if ($length + mb_strlen($text) + 1 > 1000) {
                break;
            }
            $lines[] = $text;
            $length += mb_strlen($text) + 1;
        }

        return implode("\n", $lines);
    }

    private static function change(float $now, float $before): string
    {
        if ($before <= 0) {
            return '-';
        }

        $pct = round(($now - $before) / $before * 100);

        return ($pct > 0 ? '+' : '') . $pct . '%';
    }

    private function corporationName(int $corporationId): string
    {
        return DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name') ?? 'Corporation #' . $corporationId;
    }

    private function dashboardUrl(): ?string
    {
        try {
            return route('ratting-manager.dashboard');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
