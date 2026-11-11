<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Services\Notification\Alerts\OutsidersAlert;
use RattingManager\Services\Universe\SystemKills;

/**
 * Keeps ESI's hourly kill counts for the systems the Outsiders alert
 * watches. ESI updates them once an hour, so running more often only fetches
 * the same hour again.
 */
class PollSystemKillsCommand extends Command
{
    protected $signature = 'ratting-manager:poll-system-kills';

    protected $description = 'Keep the last hour\'s NPC kills from ESI for the home space, for the Outsiders alert';

    public function handle(SystemKills $kills): int
    {
        $result = $kills->poll(app(OutsidersAlert::class)->watchedSystems());

        $result['ok'] ? $this->info($result['message']) : $this->warn($result['message']);

        return Command::SUCCESS;
    }
}
