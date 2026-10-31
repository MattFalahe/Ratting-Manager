<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Ratting\SessionBuilder;

class BuildSessionsCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:build-sessions
                            {--days=2 : How many days back to read}
                            {--from= : Read from this date instead (YYYY-MM-DD)}';

    protected $description = 'Group ticks into ratting sessions';

    public function handle(SessionBuilder $builder)
    {
        return $this->underLock($this->lockSeconds(), function () use ($builder) {
            $built = $builder->rebuild($this->since());
            $this->info("Built {$built} session(s).");

            return Command::SUCCESS;
        });
    }
}
