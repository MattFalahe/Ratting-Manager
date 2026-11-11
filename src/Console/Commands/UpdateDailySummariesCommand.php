<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Ratting\CharacterDayBuilder;
use RattingManager\Services\Ratting\DailySummaryBuilder;
use RattingManager\Services\Ratting\MonthlySummaryBuilder;
use RattingManager\Services\Ratting\RawTicks;

class UpdateDailySummariesCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:update-daily-summaries
                            {--days=2 : How many days back to read}
                            {--from= : Read from this date instead (YYYY-MM-DD)}';

    protected $description = 'Rebuild the daily summaries, and the character days and monthly summaries built with them';

    public function handle(DailySummaryBuilder $builder, CharacterDayBuilder $characterDays, MonthlySummaryBuilder $monthly)
    {
        return $this->underLock($this->lockSeconds(), function () use ($builder, $characterDays, $monthly) {
            $since = $this->since();
            $dates = CharacterDayBuilder::datesFrom($since);

            $result = $builder->rebuild($since);
            $days = $characterDays->rebuildDays($dates);
            $months = $monthly->rebuildMonths(MonthlySummaryBuilder::monthsOf($dates));
            $this->info("Wrote {$result['summaries']} summary row(s), {$result['kills']} kill row(s), {$days} character day(s) and {$months} monthly row(s).");

            $kept = RawTicks::keptFrom();
            if ($kept !== null && $since->lt($kept)) {
                $this->warn('The ticks before ' . $kept->toDateString() . ' have been dropped, so those days keep the rows they have.');
            }

            return Command::SUCCESS;
        });
    }
}
