<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Pipeline;
use RattingManager\Services\Ratting\TaxRates;

class UpdateTaxRatesCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:update-tax-rates
                            {--days=2 : How many days back to read}
                            {--from= : Read from this date instead (YYYY-MM-DD)}';

    protected $description = 'Read corporation tax rates back from paired rows and work out the net of corp-only rows';

    public function handle(TaxRates $rates, Settings $settings, Pipeline $pipeline)
    {
        return $this->underLock($this->lockSeconds(), function () use ($rates, $settings, $pipeline) {
            $corporations = $settings->configuredCorporations();
            // A day whose ticks have been dropped keeps the rate read from all
            // of them, not one read again from what is left. Nets this moves
            // wait for the next scheduled run to build on them.
            $since = $pipeline->owe($this->since())['since'];
            $days = $rates->learn($corporations, $since);
            $estimates = $rates->estimate($corporations, $since);
            $this->info("Rates read back for {$days} corporation days.");
            $this->info("Corp-only rows worked out: {$estimates['worked_out']}, waiting on a tax rate: {$estimates['no_rate']}.");

            return Command::SUCCESS;
        });
    }
}
