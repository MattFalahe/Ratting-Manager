<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Npc\NpcClassifier;
use RattingManager\Services\Npc\SpawnDetector;

class SyncNpcTypesCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:sync-npc-types';

    protected $description = 'Classify NPC types seen in bounty ticks by faction, size and rank';

    public function handle(NpcClassifier $classifier, SpawnDetector $spawns)
    {
        return $this->underLock(1800, function () use ($classifier, $spawns) {
            $written = $classifier->sync();
            $this->info("Classified {$written} new NPC type(s).");

            $changed = $classifier->reclassifyIfRulesChanged();
            if ($changed !== null) {
                $rebuilt = $spawns->rebuild();
                $this->info("The classification rules changed: {$changed} NPC type(s) classified again, {$rebuilt['removed']} special spawn(s) no longer count and {$rebuilt['found']} new one(s) found.");
            }

            return Command::SUCCESS;
        });
    }
}
