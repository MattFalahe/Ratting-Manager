<?php

namespace RattingManager\Console\Commands;

use Illuminate\Console\Command;
use RattingManager\Console\Commands\Concerns\HoldsThePipelineLock;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\TaxRates;

class SnapshotTaxRatesCommand extends Command
{
    use HoldsThePipelineLock;

    protected $signature = 'ratting-manager:snapshot-tax-rates';

    protected $description = 'Keep the current corporation tax rate for days with no paired rows';

    public function handle(TaxRates $rates, Settings $settings)
    {
        return $this->underLock(1800, function () use ($rates, $settings) {
            $written = $rates->snapshot($settings->configuredCorporations());
            $this->info("Snapshot kept for {$written} corporation(s).");

            return Command::SUCCESS;
        });
    }
}
