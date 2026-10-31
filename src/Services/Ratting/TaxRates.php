<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RattingManager\Models\CorpTaxRate;
use RattingManager\Models\EssPayout;
use RattingManager\Models\Tick;

/**
 * The corporation's tax rate on any day, and the net of rows only the
 * corporation journal saw.
 *
 * The corporation's cut is t of a payout and the character keeps the rest,
 * so on a paired row t = cut / (cut + net), and a cut on its own gives
 * net = cut * (1 - t) / t exactly. SeAT keeps only today's rate, so past
 * rates are read back from the paired rows of each day. A day with none
 * borrows the nearest day that had some, and only then the snapshot.
 */
class TaxRates
{
    /** @var array<int, array<string, float>> corporation_id => [date => rate] */
    private array $rates = [];

    /** How many rows learn() and estimate() will read, for a progress bar. */
    public function rowCount(array $corporations, Carbon $since): int
    {
        $total = 0;
        foreach ([[Tick::class, 'ticked_at'], [EssPayout::class, 'paid_at']] as [$model, $timeColumn]) {
            $total += $this->pairedRows($model, $timeColumn, $corporations, $since)->count();
            $total += $this->corporationOnlyRows($model, $timeColumn, $corporations, $since)->count();
        }

        return $total;
    }

    /** Rows seen in both journals, which give the rate the corporation took. */
    private function pairedRows(string $model, string $timeColumn, array $corporations, Carbon $since)
    {
        return $model::whereIn('corporation_id', $corporations)
            ->where('kind', 'paired')
            ->where($timeColumn, '>=', $since->copy()->startOfDay())
            ->where('net_amount', '>', 0)
            ->where('corp_tax_amount', '>', 0);
    }

    /** Rows only the corporation journal has, whose net is worked out from the cut. */
    private function corporationOnlyRows(string $model, string $timeColumn, array $corporations, Carbon $since)
    {
        return $model::whereIn('corporation_id', $corporations)
            ->where('kind', 'corporation')
            ->where($timeColumn, '>=', $since->copy()->startOfDay());
    }

    /**
     * Read the rate back from every day in the window that has paired rows.
     *
     * @param int[] $corporations
     */
    public function learn(array $corporations, Carbon $since, ?Progress $progress = null): int
    {
        $progress ??= Progress::none();
        $samples = [];

        foreach ([[Tick::class, 'ticked_at'], [EssPayout::class, 'paid_at']] as [$model, $timeColumn]) {
            $this->pairedRows($model, $timeColumn, $corporations, $since)
                ->select(['corporation_id', $timeColumn, 'net_amount', 'corp_tax_amount'])
                ->orderBy('id')
                ->chunk(5000, function ($rows) use (&$samples, $timeColumn, $progress) {
                    $progress->advance(count($rows));
                    foreach ($rows as $row) {
                        $date = $row->{$timeColumn}->toDateString();
                        $samples[$row->corporation_id][$date][] = round(
                            $row->corp_tax_amount / ($row->corp_tax_amount + $row->net_amount),
                            4
                        );
                    }
                });
        }

        $written = 0;
        foreach ($samples as $corporationId => $days) {
            foreach ($days as $date => $values) {
                CorpTaxRate::updateOrCreate(
                    ['corporation_id' => $corporationId, 'date' => $date],
                    ['tax_rate' => $this->median($values), 'source' => 'paired', 'paired_rows' => count($values)]
                );
                $written++;
            }
        }

        $this->rates = [];

        return $written;
    }

    /**
     * Keep today's rate as SeAT has it, for days that end up with no paired
     * row at all. A day that has paired rows keeps the rate read from them.
     *
     * @param int[] $corporations
     */
    public function snapshot(array $corporations): int
    {
        $today = now()->toDateString();
        $written = 0;

        $current = DB::table('corporation_infos')
            ->whereIn('corporation_id', $corporations)
            ->pluck('tax_rate', 'corporation_id');

        foreach ($current as $corporationId => $rate) {
            $row = CorpTaxRate::firstOrNew(['corporation_id' => $corporationId, 'date' => $today]);
            if ($row->exists && $row->source === 'paired') {
                continue;
            }

            $row->fill(['tax_rate' => round((float) $rate, 4), 'source' => 'snapshot', 'paired_rows' => 0])->save();
            $written++;
        }

        $this->rates = [];

        return $written;
    }

    public function rateFor(int $corporationId, $day): ?float
    {
        $date = Carbon::parse($day)->toDateString();
        $known = $this->ratesFor($corporationId);

        if (isset($known[$date])) {
            return $known[$date];
        }

        $before = null;
        $after = null;
        foreach ($known as $knownDate => $rate) {
            if ($knownDate < $date) {
                $before = $rate;
            } elseif ($after === null) {
                $after = $rate;
                break;
            }
        }

        if ($before !== null || $after !== null) {
            return $before ?? $after;
        }

        $current = DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('tax_rate');

        return $current === null ? null : round((float) $current, 4);
    }

    /**
     * Work out the net of every corporation-only row in the window.
     *
     * @param int[] $corporations
     * @return array{worked_out: int, no_rate: int}
     */
    public function estimate(array $corporations, Carbon $since, ?Progress $progress = null): array
    {
        $progress ??= Progress::none();
        $result = ['worked_out' => 0, 'no_rate' => 0];

        foreach ([[Tick::class, 'ticked_at'], [EssPayout::class, 'paid_at']] as [$model, $timeColumn]) {
            $this->corporationOnlyRows($model, $timeColumn, $corporations, $since)
                ->chunkById(1000, function ($rows) use (&$result, $timeColumn, $progress) {
                    $progress->advance(count($rows));
                    foreach ($rows as $row) {
                        $rate = $this->rateFor($row->corporation_id, $row->{$timeColumn});

                        if ($rate === null || $rate <= 0 || $rate >= 1) {
                            // A 0% corporation writes no corp rows at all, so
                            // this means the rate is not known yet.
                            $result['no_rate']++;
                            continue;
                        }

                        $row->net_amount = self::netFromCut((float) $row->corp_tax_amount, $rate);
                        $row->tax_rate = $rate;
                        $row->net_is_estimated = true;
                        try {
                            if ($row->isDirty()) {
                                $row->save();
                            }
                        } catch (QueryException $e) {
                            // Left waiting on a rate, which also keeps its day from being dropped.
                            Log::warning('Ratting Manager: could not work out the net of ' . $row->getTable() . ' row ' . $row->id . ': ' . mb_substr($e->getMessage(), 0, 300));
                            $result['no_rate']++;
                            continue;
                        }
                        $result['worked_out']++;
                    }
                });
        }

        return $result;
    }

    public static function netFromCut(float $cut, float $rate): float
    {
        return round($cut * (1 - $rate) / $rate, 2);
    }

    /** @return array<string, float> date => rate, oldest first */
    private function ratesFor(int $corporationId): array
    {
        if (!isset($this->rates[$corporationId])) {
            $this->rates[$corporationId] = CorpTaxRate::where('corporation_id', $corporationId)
                ->orderBy('date')
                ->get(['date', 'tax_rate'])
                ->mapWithKeys(fn ($row) => [$row->date->toDateString() => (float) $row->tax_rate])
                ->all();
        }

        return $this->rates[$corporationId];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 ? $values[$middle] : round(($values[$middle - 1] + $values[$middle]) / 2, 4);
    }
}
