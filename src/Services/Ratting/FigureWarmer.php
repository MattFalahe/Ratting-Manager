<?php

namespace RattingManager\Services\Ratting;

use RattingManager\Http\Controllers\ListController;
use RattingManager\Services\Configuration\Settings;

/**
 * Works out the figures the Corporation tab and the Leaderboard open with
 * right after an import, so the first director to open them does not wait
 * for the month's ticks to be read.
 */
class FigureWarmer
{
    private Settings $settings;
    private RattingMetrics $metrics;
    private Insights $insights;
    private MonthlyBreakdown $monthly;
    private ChartData $charts;

    public function __construct(Settings $settings, RattingMetrics $metrics, Insights $insights, MonthlyBreakdown $monthly, ChartData $charts)
    {
        $this->settings = $settings;
        $this->metrics = $metrics;
        $this->insights = $insights;
        $this->monthly = $monthly;
        $this->charts = $charts;
    }

    public function warm(): void
    {
        $corporations = $this->settings->configuredCorporations();
        if ($corporations === []) {
            return;
        }

        $scope = new Scope($corporations);
        $period = Period::from(null);

        $this->metrics->collect($scope, $period->start, $period->end);
        $this->insights->for($scope, $period, true);
        $this->monthly->corporationMonths($scope);

        // The rest of what the Corporation tab opens with, read the same way
        // DashboardController::corporationTab() reads it.
        $this->metrics->goneQuiet($corporations);
        $this->metrics->untracked($corporations);
        $this->charts->systems($scope, $period);
        $this->charts->spaceCounts($scope, $period);
        $this->charts->kills($scope, $period);
        $this->charts->spawns($scope, ListController::DASHBOARD_ROWS);
        $this->charts->spawnCount($scope);
        $this->charts->monthly($scope, true);
        $this->charts->daily($scope, $period);
        $this->charts->ratterMovement($scope);
    }
}
