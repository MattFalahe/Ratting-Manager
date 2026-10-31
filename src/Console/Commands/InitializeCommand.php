<?php

namespace RattingManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Character\AffiliationResolutionService;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Pipeline;
use RattingManager\Services\Ratting\Progress;
use RattingManager\Services\Ratting\RawTicks;

/**
 * First run after install: checks what SeAT holds for the configured
 * corporations, then imports the last year of it.
 *
 * SeAT keeps journal rows long after ESI's 30 days, back to when each token
 * was first added, but no page shows more than a year, so anything older
 * would only be imported to sit unread.
 */
class InitializeCommand extends Command
{
    use HoldsThePipelineLock;

    private const DAYS = 365;

    protected $signature = 'ratting-manager:initialize
                            {--from= : Import from this date instead, within the last 365 days (YYYY-MM-DD)}';

    protected $description = 'Check the setup and import the last 365 days of bounty ticks and ESS payouts SeAT holds';

    public function handle(Settings $settings, Pipeline $pipeline, AffiliationResolutionService $lookups)
    {
        $corporations = $settings->configuredCorporations();

        if ($corporations === []) {
            $this->error('No corporation is configured. Pick one under Ratting Manager > Settings, then run this again.');
            return Command::FAILURE;
        }

        $this->info('Ratting Manager - setup check');
        $oldest = null;
        $found = [];
        $missing = [];

        foreach ($corporations as $corporationId) {
            $name = DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name') ?? ('Corporation ' . $corporationId);
            $range = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->whereIn('ref_type', ['bounty_prizes', 'bounty_prize', 'ess_escrow_transfer'])
                ->selectRaw('COUNT(*) as n, MIN(date) as first, MAX(date) as last')
                ->first();

            if (!$range || (int) $range->n === 0) {
                $missing[] = $name;
                continue;
            }

            $found[] = [$name, number_format((int) $range->n), substr($range->first, 0, 10), substr($range->last, 0, 10)];
            $oldest = $oldest === null ? $range->first : min($oldest, $range->first);
        }

        if ($found !== []) {
            $this->table(['Corporation', 'Bounty and ESS rows', 'From', 'To'], $found);
        }
        foreach ($missing as $name) {
            $this->warn("  {$name}: no bounty or ESS rows in the corporation journal. A director token with wallet access is needed for untracked members to show up.");
        }

        $characterOldest = DB::table('character_wallet_journals as j')
            ->join('character_affiliations as a', 'a.character_id', '=', 'j.character_id')
            ->whereIn('a.corporation_id', $corporations)
            ->whereIn('j.ref_type', ['bounty_prizes', 'bounty_prize', 'ess_escrow_transfer'])
            ->min('j.date');

        if ($characterOldest) {
            $oldest = $oldest === null ? $characterOldest : min($oldest, $characterOldest);
        }

        if ($oldest === null && !$this->option('from')) {
            $this->warn('SeAT holds no bounty ticks for these corporations yet. Nothing to import.');
            return Command::SUCCESS;
        }

        $floor = Carbon::now()->subDays(self::DAYS)->startOfDay();
        $since = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : Carbon::parse($oldest)->startOfDay();
        if ($since->lt($floor)) {
            $this->line(($this->option('from') ? 'Asked for ' : 'SeAT holds rows from ') . $since->toDateString() . '. The pages show a year at most, so the import starts at ' . $floor->toDateString() . '.');
            $since = $floor;
        }
        $kept = RawTicks::keptFrom();
        if ($kept !== null && $since->lt($kept)) {
            $this->line('The ticks before ' . $kept->toDateString() . ' have been dropped and those days stay as they are, so the import starts there. ratting-manager:restore-ticks brings them back.');
            $since = $kept;
        }

        return $this->underLock(7200, function () use ($since, $pipeline, $lookups, $settings) {
            $this->info('Importing from ' . $since->toDateString() . '. A long history can take several minutes.');
            $this->newLine();

            // The pipeline's steps, then the two kinds of character lookup.
            [$progress, $close] = $this->progressBars(Pipeline::STEPS + 2);

            // The first import builds every session and day in the window.
            $report = $pipeline->run($since, $progress, false);
            $counts = $lookups->resolve($lookups->due(), $progress);
            $close();

            $this->newLine();
            $this->table(['Imported', ''], [
                ['Ticks from the corporation journal', number_format($report['corporation_journal']['bounty']['created'])],
                ['Ticks from members\' own journals', number_format($report['character_journal']['bounty']['created'])],
                ['Ticks found in both and paired', number_format($report['corporation_journal']['bounty']['paired'] + $report['character_journal']['bounty']['paired'])],
                ['ESS payouts', number_format($report['corporation_journal']['ess']['created'] + $report['character_journal']['ess']['created'])],
                ['Worked out from the corporation\'s cut', number_format($report['estimates']['worked_out'])],
                ['Waiting on a tax rate', number_format($report['estimates']['no_rate'])],
                ['Sessions', number_format($report['sessions'])],
                ['Daily summary rows', number_format($report['daily']['summaries'])],
                ['Character days', number_format($report['character_days'])],
                ['Monthly summary rows', number_format($report['monthly'])],
                ['Special spawns', number_format($report['special_spawns'])],
                ['New NPC types', number_format($report['npc_types'])],
                ['Characters looked up', number_format($counts['esi'] + $counts['fallback'])],
                ['Lookups left for the schedule', number_format($counts['left'])],
            ]);

            $settings->set('state.initialized_at', now()->toDateTimeString());
            $this->info('Done in ' . $report['seconds'] . 's. The scheduled run keeps it up to date from here.');

            return Command::SUCCESS;
        });
    }

    /**
     * One progress bar per step, the step's name on the line above it so the
     * bar fits an 80 column terminal. A step with nothing to do says so
     * instead of drawing an empty bar.
     *
     * @return array{0: Progress, 1: callable} the Progress to hand the run, and what to call once it is done
     */
    private function progressBars(int $steps): array
    {
        $bar = null;
        $index = 0;

        $close = function () use (&$bar) {
            if ($bar !== null) {
                $bar->finish();
                $this->newLine();
                $bar = null;
            }
        };

        $progress = new Progress(
            function (string $name, int $total) use (&$bar, &$index, $steps, $close) {
                $close();
                $label = sprintf('[%d/%d] %s', ++$index, $steps, $name);

                if ($total === 0) {
                    $this->line(' <info>' . $label . '</info>  <comment>nothing to do</comment>');
                    return;
                }

                $bar = $this->output->createProgressBar($total);
                $bar->setFormat(" <info>%message%</info>\n   %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%/%estimated:-6s%");
                $bar->setBarWidth(30);
                $bar->setMessage($label);
                $bar->start();
            },
            function (int $by) use (&$bar) {
                if ($bar !== null) {
                    $bar->advance($by);
                }
            }
        );

        return [$progress, $close];
    }
}
