<?php

namespace RattingManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Ratting\RawTicks;
use RattingManager\Support\Format;
use Symfony\Component\Console\Exception\MissingInputException;

/**
 * Brings dropped days back from SeAT's journals. Every run looks ahead first
 * and says what would change, and nothing is written until the date is typed
 * back, so there is no way to run it from a schedule or a script by accident.
 */
class RestoreTicksCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:restore-ticks
                            {--from= : Bring ticks back from this date, within the last 365 days (YYYY-MM-DD)}
                            {--dry-run : Say what would change and stop}';

    protected $description = 'Import dropped ticks again from SeAT\'s journals, after showing what would change and asking to go ahead';

    public function handle(RawTicks $ticks)
    {
        if (!$this->option('from')) {
            $this->error('Say how far back with --from=YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $from = Carbon::parse($this->option('from'), 'UTC')->startOfDay();

        $plan = null;
        $status = $this->underLock(7200, function () use ($ticks, $from, &$plan) {
            // Only a restore that went through moves the cached figures on.
            $this->dataUnchanged = true;

            $this->info('Looking ahead: this runs the real import and rolls it back, so nothing is changed yet.');
            $plan = $ticks->restore($from, false);

            if ($plan['state'] !== 'preview') {
                return $this->refused($plan);
            }

            $this->describe($plan);

            return Command::SUCCESS;
        });

        if (($plan['state'] ?? null) !== 'preview') {
            return $status;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');
            return Command::SUCCESS;
        }

        // The lock is let go while this waits: someone reading the plan must
        // not hold up the imports, and a lock that ran out behind a prompt left
        // open would let the restore start beside one. The restore looks at
        // everything again under its own lock, and still stops if a day would
        // lose ticks.
        $phrase = 'RESTORE ' . $plan['from'];
        try {
            $answer = $this->input->isInteractive() ? $this->ask("Type {$phrase} to go ahead. Anything else leaves everything as it is") : null;
        } catch (MissingInputException $e) {
            $answer = null;
        }

        if ($answer !== $phrase) {
            $this->warn('Not confirmed: nothing was changed.');
            return Command::SUCCESS;
        }

        return $this->underLock(7200, function () use ($ticks, $from) {
            $this->dataUnchanged = true;

            $done = $ticks->restore($from, true);

            if ($done['state'] !== 'restored') {
                return $this->refused($done);
            }

            $this->dataUnchanged = false;
            $this->info(sprintf(
                'Restored from %s: %d tick(s) and %d kill row(s) added. Ticks are now held from %s.',
                $done['from'], $done['added']['ticks'], $done['added']['kills'], $done['from']
            ));

            return Command::SUCCESS;
        });
    }

    private function describe(array $plan): void
    {
        $names = DB::table('corporation_infos')->whereIn('corporation_id', array_keys($plan['corporations']))->pluck('name', 'corporation_id');

        $this->newLine();
        $this->line("<info>What this covers:</info> every tracked corporation, every day from {$plan['from']} to today. Ticks are held from {$plan['line']} now, and would be held from {$plan['from']}.");

        $this->newLine();
        $this->line('<info>Rows that would be added:</info>');
        $this->table(['Table', 'Change'], array_map(
            fn ($label, $key) => [$label, sprintf('%+d', $plan['added'][$key])],
            ['Ticks', 'Kill rows', 'ESS payouts', 'Sessions (net, they are built again)', 'Special spawns'],
            ['ticks', 'kills', 'ess', 'sessions', 'spawns']
        ));

        $this->line('<info>What the figures would become, by corporation:</info>');
        $this->table(['Corporation', 'Ticks now', 'Ticks after', 'Income now', 'Income after'], array_map(
            fn ($id, $r) => [$names[$id] ?? $id, $r['ticks_before'], $r['ticks_after'], Format::isk($r['income_before']), Format::isk($r['income_after'])],
            array_keys($plan['corporations']), $plan['corporations']
        ));

        $this->line('<info>And by month:</info>');
        $this->table(['Month', 'Ticks now', 'Ticks after', 'Income now', 'Income after'], array_map(
            fn ($month, $r) => [$month, $r['ticks_before'], $r['ticks_after'], Format::isk($r['income_before']), Format::isk($r['income_after'])],
            array_keys($plan['months']), $plan['months']
        ));

        $this->line("Days that would gain ticks: {$plan['days_gaining']}. Days with the same ticks and different income: {$plan['days_income_moved']}. No day would lose ticks or income.");
        if ($plan['rows_failed'] > 0) {
            $this->warn("{$plan['rows_failed']} journal row(s) could not be written and would stay out. The log says which and why.");
        }
        $this->line("Rebuilt from the ticks: daily summaries, daily kills, character days, sessions and monthly totals from {$plan['from']} on, for every corporation.");
        $this->line("Left alone: every day before {$plan['from']}. No alert is sent for an old spawn, because the alert only looks at recent ones.");

        if ($plan['keep_days'] > 0) {
            $this->warn("Keep raw ticks is {$plan['keep_days']} days, so tonight's run deletes the older ticks again. The summaries stay as restored. Set it to 0 first to keep the ticks.");
        }
        $this->newLine();
    }

    private function refused(array $report): int
    {
        switch ($report['state']) {
            case 'nothing_dropped':
                $this->warn('No ticks have been dropped, so there is nothing to bring back. ratting-manager:initialize imports history.');
                return Command::SUCCESS;
            case 'no_corporations':
                $this->error('No corporation is tracked, so there is nothing to import. Pick one under Ratting Manager > Settings first.');
                return Command::FAILURE;
            case 'not_before_line':
                $this->warn("Ticks are already held from {$report['line']}. Give an earlier date.");
                return Command::SUCCESS;
            case 'too_old':
                $this->error("The restore reaches back to {$report['oldest']} at most, a year, since no page shows more.");
                return Command::FAILURE;
            case 'would_lose':
                $this->error('Stopped, and nothing was changed: these days would lose ticks or income, because SeAT no longer holds their journal rows or the corporation is no longer tracked.');
                $this->table(['Day', 'Corporation', 'Ticks now', 'Ticks after', 'Income now', 'Income after'], array_map(
                    fn ($r) => [$r['date'], $r['corporation'], $r['before'], $r['after'], Format::isk($r['income_before']), Format::isk($r['income_after'])],
                    array_slice($report['loses'], 0, 40)
                ));
                $last = end($report['loses'])['date'];
                $this->line('Days affected: ' . count($report['loses']) . '. Starting after the last one would avoid them: --from=' . Carbon::parse($last, 'UTC')->addDay()->toDateString());
                return Command::FAILURE;
            default:
                $this->error('Stopped, and nothing was changed: after the rebuild these days disagree with their ticks: ' . implode(', ', array_slice($report['disagree'], 0, 20)));
                return Command::FAILURE;
        }
    }
}
