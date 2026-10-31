<?php

namespace RattingManager;

use RattingManager\Console\Commands\BuildSessionsCommand;
use RattingManager\Console\Commands\ImportCharacterJournalCommand;
use RattingManager\Console\Commands\ImportCorporationJournalCommand;
use RattingManager\Console\Commands\InitializeCommand;
use RattingManager\Console\Commands\NotifyCommand;
use RattingManager\Console\Commands\PollSystemKillsCommand;
use RattingManager\Console\Commands\ProcessCommand;
use RattingManager\Console\Commands\ResolveCharactersCommand;
use RattingManager\Console\Commands\SendReportsCommand;
use RattingManager\Console\Commands\SnapshotTaxRatesCommand;
use RattingManager\Console\Commands\SyncNpcTypesCommand;
use RattingManager\Console\Commands\DropOldTicksCommand;
use RattingManager\Console\Commands\RestoreTicksCommand;
use RattingManager\Console\Commands\TraceTickCommand;
use RattingManager\Console\Commands\TrackDeparturesCommand;
use RattingManager\Console\Commands\UpdateDailySummariesCommand;
use RattingManager\Console\Commands\UpdateTaxRatesCommand;
use RattingManager\Database\Seeders\ScheduleSeeder;
use RattingManager\Http\Middleware\RequireTier;
use Seat\Services\AbstractSeatPlugin;

class RattingManagerServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }

        $this->app['router']->aliasMiddleware('ratting-manager.tier', RequireTier::class);

        $this->loadTranslationsFrom(__DIR__ . '/Resources/lang/', 'ratting-manager');
        $this->loadViewsFrom(__DIR__ . '/Resources/views/', 'ratting-manager');
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations/');

        // ISK reads the same as in Mining Manager: 1.25B ISK, 340.50M ISK,
        // 12,500 ISK. Blade directives are shared by every plugin, so this one
        // has a name of its own rather than replacing Mining Manager's @isk.
        \Illuminate\Support\Facades\Blade::directive('rmisk', function ($expression) {
            return "<?php echo \\RattingManager\\Support\\Format::isk($expression); ?>";
        });

        $this->add_publications();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessCommand::class,
                ImportCorporationJournalCommand::class,
                ImportCharacterJournalCommand::class,
                UpdateTaxRatesCommand::class,
                SnapshotTaxRatesCommand::class,
                SyncNpcTypesCommand::class,
                BuildSessionsCommand::class,
                UpdateDailySummariesCommand::class,
                ResolveCharactersCommand::class,
                InitializeCommand::class,
                TraceTickCommand::class,
                NotifyCommand::class,
                PollSystemKillsCommand::class,
                SendReportsCommand::class,
                DropOldTicksCommand::class,
                RestoreTicksCommand::class,
                TrackDeparturesCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');

        $this->registerPermissions(
            __DIR__ . '/Config/Permissions/ratting-manager.permissions.php',
            'ratting-manager'
        );

        $this->mergeConfigFrom(__DIR__ . '/Config/ratting-manager.config.php', 'ratting-manager');

        // Settings are read many times a request; one instance keeps the rows
        // it has loaded instead of asking the database again for each key.
        $this->app->singleton(\RattingManager\Services\Configuration\Settings::class);

        // Names and players for every character a page shows, looked up once
        // per request or queued job, so a long-running worker never carries an
        // old main character into a later job.
        $this->app->scoped(\RattingManager\Services\Character\Players::class);

        // Shared by Players and the importer within a request or job, so the
        // characters a page asked for are the ones its notice waits on.
        $this->app->scoped(\RattingManager\Services\Character\AffiliationResolutionService::class);

        $this->registerDatabaseSeeders([
            ScheduleSeeder::class,
        ]);
    }

    private function add_publications()
    {
        $this->publishes([
            __DIR__ . '/Config/ratting-manager.config.php' => config_path('ratting-manager.php'),
        ], ['config', 'seat']);

        $this->publishes([
            __DIR__ . '/Resources/assets' => public_path('vendor/ratting-manager'),
        ], ['public', 'seat']);
    }

    public function getName(): string
    {
        return 'Ratting Manager';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/MattFalahe/Ratting-Manager';
    }

    public function getPackagistPackageName(): string
    {
        return 'ratting-manager';
    }

    public function getPackagistVendorName(): string
    {
        return 'mattfalahe';
    }
}
