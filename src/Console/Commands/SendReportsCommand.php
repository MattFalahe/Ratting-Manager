<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RattingManager\Services\Notification\NotificationTypes;
use RattingManager\Services\Notification\Reports\RattingReports;

/**
 * Sends the Director Reports and Rankings for the last full week and month.
 * Runs daily: each post goes once per period, so the run after a week or a
 * month ends sends it, and the rest find nothing to do.
 */
class SendReportsCommand extends Command
{
    protected $signature = 'ratting-manager:send-reports
                            {--weekly : Only the weekly posts}
                            {--monthly : Only the monthly posts}
                            {--force : Send again even if this period was sent already}';

    protected $description = 'Send the weekly and monthly Director Reports and Rankings that are due';

    public function handle(RattingReports $reports): int
    {
        $lock = Cache::lock('ratting-manager:send-reports', 900);
        if (!$lock->get()) {
            $this->warn('Reports are already being sent. Skipping.');

            return Command::SUCCESS;
        }

        try {
            NotificationTypes::ensureRows();

            $cadences = [];
            if ($this->option('weekly') || !$this->option('monthly')) {
                $cadences[] = RattingReports::WEEKLY;
            }
            if ($this->option('monthly') || !$this->option('weekly')) {
                $cadences[] = RattingReports::MONTHLY;
            }

            foreach ($cadences as $cadence) {
                $sent = $reports->send($cadence, (bool) $this->option('force'));
                if ($sent === []) {
                    $this->line("  {$cadence}: nothing due");
                }
                foreach ($sent as $s) {
                    $this->line(sprintf('  %-24s corporation %d, %s: %d of %d webhook(s)', $s['type'], $s['corporation'], $s['period'], $s['delivered'], $s['bindings']));
                }
            }
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
