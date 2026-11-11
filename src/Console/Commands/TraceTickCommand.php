<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Services\Diagnostic\TickTrace;

class TraceTickCommand extends Command
{
    protected $signature = 'ratting-manager:trace-tick
                            {journal_id : The id of a bounty or ESS row in either wallet journal}';

    protected $description = 'Show what Ratting Manager did with one journal row';

    public function handle(TickTrace $trace)
    {
        foreach ($trace->trace((int) $this->argument('journal_id')) as $section) {
            $this->line('');
            $this->info($section['title'] . ' [' . $section['status'] . ']');
            $this->table([], $section['rows']);
        }

        return Command::SUCCESS;
    }
}
