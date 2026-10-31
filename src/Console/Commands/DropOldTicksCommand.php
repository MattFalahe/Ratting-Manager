<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Ratting\RawTicks;

class DropOldTicksCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:drop-old-ticks
                            {--dry-run : Say what would go and delete nothing}';

    protected $description = 'Delete raw rows older than the days set under Keep raw ticks, once the summaries hold their days, and everything older than a year';

    private const NAMES = [
        'ticks' => 'tick(s)',
        'kills' => 'kill row(s)',
        'payouts' => 'ESS payout(s)',
        'daily_kills' => 'daily kill total(s)',
        'daily_summaries' => 'daily summary row(s)',
        'character_days' => 'character day(s)',
        'sessions' => 'session(s)',
        'special_spawns' => 'special spawn(s)',
        'tax_rates' => 'tax rate(s)',
        'departures' => 'departure(s)',
        'monthly_summaries' => 'monthly summary row(s)',
    ];

    public function handle(RawTicks $ticks)
    {
        return $this->underLock(7200, function () use ($ticks) {
            $dryRun = (bool) $this->option('dry-run');
            $report = $ticks->drop($dryRun);

            // Raw rows are not what the figures are read from, so those stay
            // as they are. Days older than a year are gone from what the pages
            // compare with, so their cached figures are not kept.
            $this->dataUnchanged = $dryRun || ($report['older_than_a_year'] ?? []) === [];

            switch ($report['state']) {
                case 'not_ready':
                    $this->warn('The character days and monthly summaries are not filled yet, so nothing is dropped.');
                    return Command::SUCCESS;
                case 'build_owed':
                    $this->warn('The last import did not finish building what it wrote, so nothing is dropped until a run gets to the end.');
                    return Command::SUCCESS;
                case 'off':
                    $this->line('Keep raw ticks is 0, so raw rows stay for the year the pages can show.');
                    break;
                case 'nothing':
                    $this->line("No raw rows older than {$report['until']} to drop.");
                    break;
                case 'would_drop':
                    $this->info("Would delete {$this->listed($report)} older than {$report['until']}.");
                    break;
                default:
                    $this->info("Deleted {$this->listed($report)} older than {$report['until']}.");
            }

            if ($report['older_than_a_year'] !== []) {
                $this->info(($dryRun ? 'Would delete ' : 'Deleted ') . $this->listed($report['older_than_a_year']) . ' from before what any page shows.');
            }

            if (($report['held_by'] ?? null) !== null) {
                $this->warn("Stopped at {$report['held_by']}: its summaries disagree with its ticks or payouts, or one of them still waits on a tax rate. Data Integrity has more.");
            }

            return Command::SUCCESS;
        });
    }

    private function listed(array $counts): string
    {
        $parts = [];
        foreach (self::NAMES as $key => $name) {
            if (($counts[$key] ?? 0) > 0) {
                $parts[] = number_format($counts[$key]) . ' ' . $name;
            }
        }

        return implode(', ', $parts);
    }
}
