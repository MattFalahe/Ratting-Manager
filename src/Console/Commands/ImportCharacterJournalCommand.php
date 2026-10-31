<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Import\JournalImporter;
use RattingManager\Services\Ratting\Pipeline;

class ImportCharacterJournalCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:import-character-journal
                            {--days=2 : How many days back to read}
                            {--from= : Read from this date instead (YYYY-MM-DD)}';

    protected $description = 'Import bounty ticks and ESS payouts from the wallet journals of members';

    public function handle(JournalImporter $importer, Pipeline $pipeline)
    {
        return $this->underLock($this->lockSeconds(), function () use ($importer, $pipeline) {
            // Rows this writes wait for the next scheduled run to build on them.
            $pipeline->owe($this->since());
            $report = $importer->importCharacter($this->since());
            $this->printReport($report);

            return Command::SUCCESS;
        });
    }

    private function printReport(array $report): void
    {
        if (isset($report['note'])) {
            $this->warn($report['note']);
            return;
        }

        foreach (['bounty' => 'Ticks', 'ess' => 'ESS payouts'] as $stream => $label) {
            $r = $report[$stream];
            $this->line(sprintf('%s: %d read, %d new, %d paired, %d updated', $label, $r['read'], $r['created'], $r['paired'], $r['updated']));
            if ($r['failed'] > 0) {
                $this->warn("  {$r['failed']} row(s) could not be written. The log says which and why.");
            }
            foreach ($r['skipped'] as $why => $count) {
                $this->line("  left out ({$why}): {$count}");
            }
        }
    }
}
