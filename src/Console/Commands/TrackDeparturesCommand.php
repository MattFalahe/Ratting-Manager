<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RattingManager\Services\Character\Departures;

/**
 * Finds out exactly when ratters left a tracked corporation, so pages can say
 * so without calling out to anything.
 */
class TrackDeparturesCommand extends Command
{
    protected $signature = 'ratting-manager:track-departures';

    protected $description = 'Look up when ratters who have moved left their tracked corporation';

    public function handle(Departures $departures): int
    {
        // Its own lock, not the import's: this only touches the departures
        // table, and should not make an import step aside.
        $lock = Cache::lock('ratting-manager:track-departures', 1800);
        if (!$lock->get()) {
            $this->warn('Another lookup of departures is running. Skipping.');

            return Command::SUCCESS;
        }

        try {
            $counts = $departures->track();
            $this->info(sprintf(
                'Departures: %d read from a corporation history, %d with no history yet, %d left for the next run.',
                $counts['exact'], $counts['seen'], $counts['left']
            ));

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
