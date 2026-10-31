<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RattingManager\Services\Notification\Alerts\Alerts;
use RattingManager\Services\Notification\AlertState;
use RattingManager\Services\Notification\NotificationTypes;
use RattingManager\Services\Notification\WebhookDeliveryService;

/**
 * Checks every alert and sends what is new. Each alert remembers what it has
 * sent, so running this often never sends the same thing twice.
 */
class NotifyCommand extends Command
{
    protected $signature = 'ratting-manager:notify
                            {--only= : One alert type, such as special_spawn}';

    protected $description = 'Send Ratting Manager alerts: special spawns, ships lost, outsiders, stalled data, untracked ratters and players gone quiet';

    public function handle(): int
    {
        $only = $this->option('only');
        if ($only && !isset(Alerts::CLASSES[$only])) {
            $this->error('Unknown alert type. One of: ' . implode(', ', array_keys(Alerts::CLASSES)));

            return Command::FAILURE;
        }

        // Its own lock: alerts only read the ratting data, so they need not
        // wait for an import, but two runs at once could send twice.
        $lock = Cache::lock('ratting-manager:notify', 900);
        if (!$lock->get()) {
            $this->warn('Another alert run is going. Skipping.');

            return Command::SUCCESS;
        }

        try {
            NotificationTypes::ensureRows();

            foreach (Alerts::CLASSES as $type => $class) {
                if ($only && $only !== $type) {
                    continue;
                }

                try {
                    $sent = Alerts::make($type)->run();
                    $this->line(sprintf('  %-18s %d post(s)', $type, $sent));
                } catch (\Throwable $e) {
                    // One alert failing must not stop the others.
                    report($e);
                    $this->error(sprintf('  %-18s failed: %s', $type, $e->getMessage()));
                }
            }

            WebhookDeliveryService::prune();
            app(AlertState::class)->prune();
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
