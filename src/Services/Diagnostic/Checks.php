<?php

namespace RattingManager\Services\Diagnostic;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RattingManager\Services\Character\AffiliationResolutionService;
use RattingManager\Services\Character\Departures;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Configuration\SettingsRegistry;
use RattingManager\Services\Npc\NpcRegistry;
use RattingManager\Services\Ratting\CharacterDays;
use RattingManager\Services\Ratting\RawTicks;
use RattingManager\Services\Ratting\Summaries;
use RattingManager\Services\Ratting\TaxRates;
use RattingManager\Services\Ratting\WalletGaps;
use RattingManager\Services\Universe\HomeSpace;

/**
 * Every check the Diagnostics page runs. All of them only read.
 *
 * A check is ['label', 'status' => ok|warn|info, 'message'], with optional
 * 'details' for the rows behind it. Groups carry a title and a line saying
 * what they are for.
 */
class Checks
{
    public const SEAT_TABLES = [
        'corporation_wallet_journals', 'character_wallet_journals', 'character_affiliations',
        'character_corporation_histories', 'corporation_infos', 'refresh_tokens', 'users',
        'character_infos', 'invTypes', 'invGroups', 'solar_systems', 'constellations', 'regions',
    ];

    public const PLUGIN_TABLES = [
        'ratting_manager_settings', 'ratting_manager_ticks', 'ratting_manager_tick_kills',
        'ratting_manager_ess_payouts', 'ratting_manager_sessions', 'ratting_manager_daily_summaries',
        'ratting_manager_daily_kills', 'ratting_manager_npc_types', 'ratting_manager_special_spawns',
        'ratting_manager_corp_tax_rates', 'ratting_manager_character_affiliations',
        'ratting_manager_webhooks', 'ratting_manager_notification_types', 'ratting_manager_type_webhook',
        'ratting_manager_webhook_deliveries', 'ratting_manager_alert_state', 'ratting_manager_system_kills',
        'ratting_manager_character_days', 'ratting_manager_monthly_summaries', 'ratting_manager_departures',
    ];

    public const COMMANDS = [
        'ratting-manager:process',
        'ratting-manager:process --days=31',
        'ratting-manager:resolve-characters --requested',
        'ratting-manager:resolve-characters',
        'ratting-manager:notify',
        'ratting-manager:poll-system-kills',
        'ratting-manager:send-reports',
        'ratting-manager:track-departures',
        'ratting-manager:drop-old-ticks',
    ];

    private Settings $settings;
    private Players $players;
    private TaxRates $taxRates;

    public function __construct(Settings $settings, Players $players, TaxRates $taxRates)
    {
        $this->settings = $settings;
        $this->players = $players;
        $this->taxRates = $taxRates;
    }

    /** The Health Checks tab: quick, and safe to run on every page load. */
    public function health(): array
    {
        return [
            $this->environment(),
            $this->seatTables(),
            $this->pluginTables(),
            $this->corporations(),
            $this->schedules(),
            $this->lastImport(),
            $this->characterLookups(),
            $this->notifications(),
            $this->systemKills(),
        ];
    }

    /** The Master Test: everything from the journals to the figures, grouped. */
    public function masterTest(): array
    {
        $corporations = $this->settings->configuredCorporations();

        return [
            [
                'title' => 'Data coming in',
                'description' => 'Whether SeAT is pulling the journals the plugin reads, and whether the plugin is reading them.',
                'checks' => array_merge(
                    [$this->corporations(), $this->lastImport(), $this->characterLookups()],
                    $this->corporationJournals($corporations),
                    [$this->walletTokens($corporations)]
                ),
            ],
            [
                'title' => 'Figures',
                'description' => 'Whether income worked out from the corporation\'s cut matches what characters really received.',
                'checks' => [
                    $this->formulaAgainstPairs($corporations),
                    $this->ratesKnown($corporations),
                    $this->waitingOnRate($corporations),
                    $this->essRateAgrees($corporations),
                ],
            ],
            [
                'title' => 'NPCs',
                'description' => 'Rats the plugin could not place by faction and size. They still count; they just show as Other.',
                'checks' => [$this->unknownNpcs(), $this->npcsByMass()],
            ],
            [
                'title' => 'Plugin setup',
                'description' => 'Tables and schedules.',
                'checks' => [$this->pluginTables(), $this->schedules()],
            ],
        ];
    }

    /** Every setting with its value, its default and where it applies. */
    public function settingsHealth(): array
    {
        $stored = DB::table('ratting_manager_settings')->whereNull('corporation_id')->pluck('value', 'key')->all();
        $rows = [];

        foreach (SettingsRegistry::keys() as $key) {
            $value = $this->settings->get($key);
            $rows[] = [
                'key' => $key,
                'scope' => SettingsRegistry::scope($key),
                'value' => is_array($value) ? json_encode($value) : var_export($value, true),
                'default' => json_encode(SettingsRegistry::default($key)),
                'stored' => array_key_exists($key, $stored),
            ];
        }

        $checks = [];
        $gap = (int) $this->settings->get('data.session_gap_minutes');
        $checks[] = $gap < 25
            ? ['label' => 'Session gap', 'status' => 'warn', 'message' => "{$gap} minutes is shorter than a tick and a little slack, so ordinary ratting splits into many sessions."]
            : ['label' => 'Session gap', 'status' => 'ok', 'message' => "{$gap} minutes: one missed tick does not split a session."];

        $quiet = (int) $this->settings->get('data.gone_quiet_days');
        $regular = (int) $this->settings->get('data.regular_ratter_days');
        $checks[] = $quiet >= $regular
            ? ['label' => 'Gone quiet', 'status' => 'warn', 'message' => "Gone quiet ({$quiet} days) is not shorter than the regular window ({$regular} days), so the list is always empty."]
            : ['label' => 'Gone quiet', 'status' => 'ok', 'message' => "Players who ratted in the last {$regular} days and not in the last {$quiet}."];

        $unknownKeys = DB::table('ratting_manager_settings')->pluck('key')->reject(fn ($k) => SettingsRegistry::has($k))->values()->all();
        $checks[] = $unknownKeys === []
            ? ['label' => 'Stored keys', 'status' => 'ok', 'message' => 'Every stored setting is one the plugin reads.']
            : ['label' => 'Stored keys', 'status' => 'warn', 'message' => 'Stored but not read by anything: ' . implode(', ', $unknownKeys)];

        return ['rows' => $rows, 'checks' => $checks];
    }

    /** Consistency between the tables. */
    public function dataIntegrity(): array
    {
        $kinds = DB::table('ratting_manager_ticks')->selectRaw('kind, COUNT(*) as n')->groupBy('kind')->pluck('n', 'kind')->all();
        $essKinds = DB::table('ratting_manager_ess_payouts')->selectRaw('kind, COUNT(*) as n')->groupBy('kind')->pluck('n', 'kind')->all();

        $checks = [];
        $checks[] = [
            'label' => 'Ticks by source',
            'status' => 'info',
            'message' => sprintf(
                'Paired %d, own journal only %d, corporation only %d. ESS payouts: paired %d, own journal only %d, corporation only %d.',
                $kinds['paired'] ?? 0, $kinds['character'] ?? 0, $kinds['corporation'] ?? 0,
                $essKinds['paired'] ?? 0, $essKinds['character'] ?? 0, $essKinds['corporation'] ?? 0
            ) . (RawTicks::keptFrom() ? ' Counted from ' . RawTicks::keptFrom()->toDateString() . ', the ones still kept.' : ''),
        ];
        $checks[] = $this->rawTicks();
        $checks[] = $this->tableSizes();
        $checks[] = $this->walletGaps();
        $checks[] = $this->departures();

        $noSession = DB::table('ratting_manager_ticks')->whereNull('session_id')->where('ticked_at', '<', now()->subHours(2))->count();
        $checks[] = $noSession === 0
            ? ['label' => 'Ticks in a session', 'status' => 'ok', 'message' => 'Every tick older than two hours belongs to a session.']
            : ['label' => 'Ticks in a session', 'status' => 'warn', 'message' => "{$noSession} ticks older than two hours have no session. The next run of ratting-manager:build-sessions over those days places them."];

        $noKills = DB::table('ratting_manager_ticks as t')
            ->leftJoin('ratting_manager_tick_kills as k', 'k.tick_id', '=', 't.id')
            ->whereNotNull('t.kill_group')->whereNull('k.id')->count();
        $checks[] = $noKills === 0
            ? ['label' => 'Kill lists', 'status' => 'ok', 'message' => 'Every tick with a kill list has its kills stored.']
            : ['label' => 'Kill lists', 'status' => 'warn', 'message' => "{$noKills} ticks have a kill group but no kills stored."];

        $daysWithTicks = DB::table('ratting_manager_ticks')->where('ticked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(ticked_at) as d')->distinct()->pluck('d')->all();
        $daysSummarised = DB::table('ratting_manager_daily_summaries')->where('date', '>=', now()->subDays(30)->toDateString())
            ->distinct()->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $missing = array_values(array_diff($daysWithTicks, $daysSummarised));
        $checks[] = $missing === []
            ? ['label' => 'Daily summaries', 'status' => 'ok', 'message' => 'Every day with ticks in the last 30 days has its summary.']
            : ['label' => 'Daily summaries', 'status' => 'warn', 'message' => 'Days with ticks but no summary: ' . implode(', ', $missing) . '. ratting-manager:update-daily-summaries --from=' . min($missing) . ' builds them.'];

        $checks = array_merge($checks, $this->builtSummaries());

        $noSystem = DB::table('ratting_manager_ess_payouts')->whereNull('solar_system_id')->count();
        $checks[] = [
            'label' => 'ESS payouts without a system',
            'status' => $noSystem === 0 ? 'ok' : 'info',
            'message' => $noSystem === 0 ? 'Every ESS payout has its system.' : "{$noSystem} ESS payouts came with no system and show as System not given.",
        ];

        return $checks;
    }

    /** How long raw rows are kept, and what the last drop did. */
    private function rawTicks(): array
    {
        $days = (int) $this->settings->get('data.raw_tick_days');
        $kept = RawTicks::keptFrom();
        $last = $this->settings->get('state.last_raw_drop');
        $year = $this->settings->get('state.last_year_drop');

        $message = $days === 0
            ? 'Ticks, kill rows, ESS payouts and daily kill totals stay for the ' . RawTicks::KEEP_DAYS . ' days the pages can show: Keep raw ticks is 0.'
            : "Ticks, kill rows, ESS payouts and daily kill totals older than {$days} days are deleted nightly, once the summaries hold their days.";
        $message .= ' Daily summaries, character days and sessions go after ' . RawTicks::KEEP_DAYS . ' days, monthly summaries after ' . RawTicks::KEEP_MONTHS . ' months.';
        if ($kept !== null) {
            $message .= ' Days before ' . $kept->toDateString() . ' are only in the summaries and cannot be rebuilt, except through ratting-manager:restore-ticks.';
        }
        if (is_array($last)) {
            $message .= " The last drop, {$last['at']}, deleted {$last['ticks']} ticks and {$last['kills']} kill rows.";
        }
        if (is_array($year)) {
            $message .= " The last clear-out of days older than a year was {$year['at']}.";
        }
        if (is_array($last) && $last['held_by'] !== null) {
            return ['label' => 'Raw ticks', 'status' => 'warn', 'message' => $message . " It stopped at {$last['held_by']}, where the summaries disagree with the ticks or payouts, or one of them waits on a tax rate. ratting-manager:update-daily-summaries --from={$last['held_by']} builds that day again."];
        }

        return ['label' => 'Raw ticks', 'status' => 'info', 'message' => $message];
    }

    /** What the plugin's tables take up, biggest first, so a slow server can see where it goes. */
    private function tableSizes(): array
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('table_name', self::PLUGIN_TABLES)
            ->selectRaw('table_name as name, table_rows as row_count, data_length + index_length as bytes')
            ->orderByDesc('bytes')
            ->get();

        return [
            'label' => 'Table sizes',
            'status' => 'info',
            'message' => sprintf('%.1f MB in all, rows approximate.', $tables->sum('bytes') / 1048576),
            'details' => $tables->take(8)->mapWithKeys(fn ($t) => [
                $t->name => sprintf('%.1f MB, %s rows', $t->bytes / 1048576, number_format((int) $t->row_count)),
            ])->all(),
        ];
    }

    /** Characters whose wallet token has gone, and how long there is to fix it. */
    private function walletGaps(): array
    {
        $gaps = app(WalletGaps::class)->forCorporations($this->settings->configuredCorporations());

        if ($gaps === []) {
            return ['label' => 'Wallet tokens lost', 'status' => 'ok', 'message' => 'No ratter with a wallet token has lost it, and every token\'s journal is coming in.'];
        }

        $first = $gaps[0];

        return [
            'label' => 'Wallet tokens lost',
            'status' => 'warn',
            'message' => sprintf(
                '%d character(s), listed on the Corporation tab. The soonest is %s, gone since %s: back by %s and nothing is lost. Up to 30 days fill in on the next import once a token is back; ESI serves no more than that.',
                count($gaps), $first['name'], substr($first['since'], 0, 10), substr($first['fix_by'], 0, 10)
            ),
        ];
    }

    /** How many moves out of a tracked corporation are on file, and which are only as good as when they were noticed. */
    private function departures(): array
    {
        $kept = DB::table(Departures::TABLE)->count();
        $seen = DB::table(Departures::TABLE)->where('source', Departures::SEEN)->count();
        $due = count(app(Departures::class)->due());

        return [
            'label' => 'Departures',
            'status' => 'info',
            'message' => sprintf(
                '%d on file, %d of them only as good as when the move was noticed because no corporation history answered. %d to look up on the next run of ratting-manager:track-departures.',
                $kept, $seen, $due
            ),
        ];
    }

    /** Whether the character days and monthly summaries still agree with what they are built from. */
    private function builtSummaries(): array
    {
        if (!$this->settings->get('state.summaries_built')) {
            return [[
                'label' => 'Character days and monthly summaries',
                'status' => 'info',
                'message' => 'Not filled yet. The next import fills them from what is on file, and the pages read the ticks and daily summaries until then.',
            ]];
        }

        $from = now()->subDays(30)->toDateString();
        $ticks = DB::table('ratting_manager_ticks')->where('ticked_at', '>=', $from)
            ->selectRaw('DATE(ticked_at) as day, COUNT(*) as n')->groupBy('day')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->day, 0, 10) => (int) $r->n])->all();
        $kept = DB::table(CharacterDays::TABLE)->where('date', '>=', $from)
            ->selectRaw('date, SUM(tick_count) as n')->groupBy('date')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->date, 0, 10) => (int) $r->n])->all();
        $days = array_keys(array_diff_assoc($ticks, $kept) + array_diff_assoc($kept, $ticks));
        sort($days);

        $fromMonth = now()->subMonthsNoOverflow(11)->format('Y-m');
        $byMonth = fn ($query) => $query->get()->mapWithKeys(fn ($r) => [$r->month => $r->ticks . ':' . $r->income])->all();
        $daily = $byMonth(DB::table(Summaries::DAILY)->where('date', '>=', $fromMonth . '-01')
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(ticks) as ticks, SUM(bounty_net + ess_net) as income")->groupBy('month'));
        $monthly = $byMonth(DB::table(Summaries::MONTHLY)->where('month', '>=', $fromMonth)
            ->selectRaw('month, SUM(ticks) as ticks, SUM(bounty_net + ess_net) as income')->groupBy('month'));
        $months = array_keys(array_diff_assoc($daily, $monthly) + array_diff_assoc($monthly, $daily));
        sort($months);

        return [
            $days === []
                ? ['label' => 'Character days', 'status' => 'ok', 'message' => 'Every day of the last 30 holds the ticks the tick table has for it.']
                : ['label' => 'Character days', 'status' => 'warn', 'message' => 'Days that disagree with the tick table: ' . implode(', ', $days) . '. ratting-manager:update-daily-summaries --from=' . $days[0] . ' builds them again.'],
            $months === []
                ? ['label' => 'Monthly summaries', 'status' => 'ok', 'message' => 'Every month of the last 12 adds up to its daily summaries.']
                : ['label' => 'Monthly summaries', 'status' => 'warn', 'message' => 'Months that disagree with their daily summaries: ' . implode(', ', $months) . '. ratting-manager:update-daily-summaries --from=' . $months[0] . '-01 builds them again.'],
        ];
    }

    private function environment(): array
    {
        return [
            'label' => 'Environment',
            'status' => date_default_timezone_get() === 'UTC' ? 'ok' : 'warn',
            'message' => date_default_timezone_get() === 'UTC'
                ? 'PHP ' . PHP_VERSION . ', Laravel ' . app()->version() . ', times in UTC (EVE time).'
                : 'The app timezone is ' . date_default_timezone_get() . ', not UTC. Every EVE time on the pages will be off by the difference.',
        ];
    }

    private function seatTables(): array
    {
        $missing = array_values(array_filter(self::SEAT_TABLES, fn ($t) => !Schema::hasTable($t)));

        return $missing === []
            ? ['label' => 'SeAT and SDE tables', 'status' => 'ok', 'message' => 'All ' . count(self::SEAT_TABLES) . ' tables the plugin reads are there.']
            : ['label' => 'SeAT and SDE tables', 'status' => 'warn', 'message' => 'Missing: ' . implode(', ', $missing) . '. Pages that need them will be empty.'];
    }

    private function pluginTables(): array
    {
        // The database's own estimate: this runs on every page load, and counting
        // the rows of a big table reads a whole index.
        $details = DB::table('information_schema.tables')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('table_name', self::PLUGIN_TABLES)
            ->selectRaw('table_name as name, table_rows as row_count')
            ->pluck('row_count', 'name')
            ->map(fn ($rows) => (int) $rows)
            ->all();
        $missing = array_values(array_diff(self::PLUGIN_TABLES, array_keys($details)));

        return [
            'label' => 'Plugin tables',
            'status' => $missing === [] ? 'ok' : 'warn',
            'message' => $missing === []
                ? 'All ' . count(self::PLUGIN_TABLES) . ' tables are there. Rows are approximate.'
                : 'Missing: ' . implode(', ', $missing) . '. The migrations have not run; restarting the SeAT containers runs them.',
            'details' => $details,
        ];
    }

    private function corporations(): array
    {
        $configured = $this->settings->configuredCorporations();
        if ($configured === []) {
            return ['label' => 'Configured corporations', 'status' => 'warn', 'message' => 'None. Pick the corporations to track on the Settings page.'];
        }

        $names = DB::table('corporation_infos')->whereIn('corporation_id', $configured)->pluck('name')->all();

        return ['label' => 'Configured corporations', 'status' => 'ok', 'message' => implode(', ', $names ?: $configured)];
    }

    private function schedules(): array
    {
        if (!Schema::hasTable('schedules')) {
            return ['label' => 'Schedules', 'status' => 'info', 'message' => 'SeAT\'s schedules table is not there to check.'];
        }

        $present = DB::table('schedules')->where('command', 'like', 'ratting-manager:%')->pluck('command')->all();
        $missing = array_values(array_diff(self::COMMANDS, $present));

        return $missing === []
            ? ['label' => 'Schedules', 'status' => 'ok', 'message' => 'All ' . count(self::COMMANDS) . ' schedules are in place.']
            : ['label' => 'Schedules', 'status' => 'warn', 'message' => 'Not scheduled: ' . implode(', ', $missing) . '. SeAT seeds them when its containers restart.'];
    }

    /**
     * How the background lookups for ratters SeAT does not know are doing.
     * A page that asked for a character waits on the minute run, so anything
     * still waiting after a few minutes means that run is not happening.
     */
    private function characterLookups(): array
    {
        if (!Schema::hasTable(AffiliationResolutionService::TABLE)) {
            return ['label' => 'Character lookups', 'status' => 'info', 'message' => 'The lookups table is not there yet.'];
        }

        $stats = app(AffiliationResolutionService::class)->stats();
        $stuck = DB::table(AffiliationResolutionService::TABLE)
            ->where('source', AffiliationResolutionService::PENDING)
            ->where('requested_at', '<', now()->subMinutes(5))
            ->count();

        $message = sprintf(
            '%d named from journal rows, %d looked up from ESI, %d from EVEWho or zKillboard, %d rejected by ESI, %d waiting.',
            $stats[AffiliationResolutionService::JOURNAL] ?? 0,
            $stats['esi'] ?? 0,
            ($stats['evewho'] ?? 0) + ($stats['zkillboard'] ?? 0),
            $stats[AffiliationResolutionService::INVALID] ?? 0,
            $stats[AffiliationResolutionService::PENDING] ?? 0
        );

        return $stuck > 0
            ? ['label' => 'Character lookups', 'status' => 'warn', 'message' => $message . " {$stuck} have waited more than five minutes, so ratting-manager:resolve-characters --requested may not be running.", 'details' => $stats]
            : ['label' => 'Character lookups', 'status' => 'ok', 'message' => $message, 'details' => $stats];
    }

    private function lastImport(): array
    {
        $last = $this->settings->get('state.last_process_at');
        if (!$last) {
            return ['label' => 'Last import', 'status' => 'warn', 'message' => 'The import has never run. Run ratting-manager:initialize, or wait for the schedule.'];
        }

        $age = Carbon::parse($last)->diffInMinutes(now());

        if ($age > 90) {
            return ['label' => 'Last import', 'status' => 'warn', 'message' => "{$last} UTC, {$age} minutes ago. It runs every half hour, so the schedule may have stopped."];
        }

        // A run in progress has this set too, so only one older than the half
        // hour between runs means something stopped.
        $owed = $this->settings->get('state.build_owed');
        if (is_array($owed) && Carbon::parse($owed['touched_since'])->diffInMinutes(now()) > 35) {
            return ['label' => 'Last import', 'status' => 'warn', 'message' => "A run has not reached the end since {$owed['touched_since']} UTC. The next one to finish builds everything it left, and old ticks are not dropped until then. The log has the error."];
        }

        $report = $this->settings->get('state.last_process_report');
        $failed = 0;
        foreach (['corporation_journal', 'character_journal'] as $journal) {
            foreach (['bounty', 'ess'] as $stream) {
                $failed += $report[$journal][$stream]['failed'] ?? 0;
            }
        }

        return $failed > 0
            ? ['label' => 'Last import', 'status' => 'warn', 'message' => "{$last} UTC, {$age} minutes ago, but {$failed} journal row(s) could not be written and were left out. The log says which and why, and each run tries them again."]
            : ['label' => 'Last import', 'status' => 'ok', 'message' => "{$last} UTC, {$age} minutes ago."];
    }

    private function corporationJournals(array $corporations): array
    {
        $checks = [];
        foreach ($corporations as $corporationId) {
            $name = DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name') ?? (string) $corporationId;
            $last = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->whereIn('ref_type', ['bounty_prizes', 'bounty_prize'])
                ->orderByDesc('date')
                ->limit(1)
                ->value('date');

            if (!$last) {
                $checks[] = ['label' => "Corporation journal: {$name}", 'status' => 'warn', 'message' => 'No bounty rows at all. Either nobody rats, the corporation tax is 0%, or SeAT has no director token with wallet access for it. Without it, members with no wallet token are not seen.'];
                continue;
            }

            $hours = Carbon::parse($last)->diffInHours(now());
            $checks[] = $hours > 48
                ? ['label' => "Corporation journal: {$name}", 'status' => 'warn', 'message' => "The newest bounty row is from {$last}, {$hours} hours ago. Check the director token if members have been ratting since."]
                : ['label' => "Corporation journal: {$name}", 'status' => 'ok', 'message' => "The newest bounty row is from {$last}."];
        }

        return $checks;
    }

    private function walletTokens(array $corporations): array
    {
        $ratters = DB::table('ratting_manager_ticks')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->where('ticked_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->players->load($ratters);
        $withWallet = count(array_filter($ratters, fn ($id) => $this->players->hasWalletToken($id)));

        return [
            'label' => 'Wallet tokens',
            'status' => 'info',
            'message' => sprintf('%d of the %d characters who ratted in the last 30 days have a wallet token. The rest are seen through the corporation journal only.', $withWallet, count($ratters)),
        ];
    }

    private function formulaAgainstPairs(array $corporations): array
    {
        $rows = DB::table('ratting_manager_ticks')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->where('kind', 'paired')
            ->where('ticked_at', '>=', now()->subDays(30))
            ->where('corp_tax_amount', '>', 0)
            ->orderByDesc('ticked_at')
            ->limit(2000)
            ->get(['corporation_id', 'ticked_at', 'net_amount', 'corp_tax_amount']);

        if ($rows->isEmpty()) {
            return ['label' => 'Worked-out income', 'status' => 'info', 'message' => 'No paired ticks in the last 30 days to check the sum against. Pairs need members with wallet tokens.'];
        }

        $worst = 0.0;
        foreach ($rows as $row) {
            $rate = $this->taxRates->rateFor((int) $row->corporation_id, $row->ticked_at);
            if (!$rate) {
                continue;
            }
            $worked = TaxRates::netFromCut((float) $row->corp_tax_amount, $rate);
            $worst = max($worst, abs($worked - (float) $row->net_amount) / max(1.0, (float) $row->net_amount));
        }

        $percent = round($worst * 100, 2);

        return $percent <= 0.5
            ? ['label' => 'Worked-out income', 'status' => 'ok', 'message' => "Worked out from the cut, {$rows->count()} paired ticks come within {$percent}% of what the characters received."]
            : ['label' => 'Worked-out income', 'status' => 'warn', 'message' => "The worst of {$rows->count()} paired ticks is {$percent}% off what the character received. A tax rate change part way through a day does this; anything else is worth a Tick Trace."];
    }

    private function ratesKnown(array $corporations): array
    {
        $unknown = array_filter($corporations, fn ($id) => $this->taxRates->rateFor($id, now()) === null);

        return $unknown === []
            ? ['label' => 'Tax rates', 'status' => 'ok', 'message' => 'Every configured corporation has a known rate for today.']
            : ['label' => 'Tax rates', 'status' => 'warn', 'message' => 'No rate known yet for: ' . implode(', ', $unknown) . '.'];
    }

    private function waitingOnRate(array $corporations): array
    {
        $waiting = DB::table('ratting_manager_ticks')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->where('kind', 'corporation')
            ->whereNull('tax_rate')
            ->count();

        return $waiting === 0
            ? ['label' => 'Corporation-only ticks', 'status' => 'ok', 'message' => 'Every one has its income worked out.']
            : ['label' => 'Corporation-only ticks', 'status' => 'warn', 'message' => "{$waiting} are waiting on a tax rate, so their income shows as zero until one is known."];
    }

    private function essRateAgrees(array $corporations): array
    {
        $rows = DB::table('ratting_manager_ess_payouts')
            ->whereIn('corporation_id', $corporations ?: [0])
            ->where('kind', 'paired')
            ->where('corp_tax_amount', '>', 0)
            ->orderByDesc('paid_at')
            ->limit(200)
            ->get(['corporation_id', 'paid_at', 'net_amount', 'corp_tax_amount']);

        if ($rows->isEmpty()) {
            return ['label' => 'ESS rate', 'status' => 'info', 'message' => 'No paired ESS payouts yet to compare with the bounty rate.'];
        }

        $off = 0;
        foreach ($rows as $row) {
            $rate = $this->taxRates->rateFor((int) $row->corporation_id, $row->paid_at);
            $essRate = $row->corp_tax_amount / ($row->corp_tax_amount + $row->net_amount);
            if ($rate && abs($essRate - $rate) > 0.005) {
                $off++;
            }
        }

        return $off === 0
            ? ['label' => 'ESS rate', 'status' => 'ok', 'message' => "The corporation takes the same rate of ESS payouts as of bounties ({$rows->count()} paired payouts checked)."]
            : ['label' => 'ESS rate', 'status' => 'warn', 'message' => "{$off} of {$rows->count()} paired ESS payouts were taxed at a different rate from bounties that day."];
    }

    private function unknownNpcs(): array
    {
        $unknown = DB::table('ratting_manager_npc_types as n')
            ->join('ratting_manager_daily_kills as k', 'k.npc_type_id', '=', 'n.type_id')
            ->where('k.date', '>=', now()->subDays(30)->toDateString())
            ->where(fn ($q) => $q->where('n.classified_by', 'unknown')->orWhere('n.faction', 'other'))
            ->groupBy('n.type_id', 'n.name', 'n.group_name')
            ->selectRaw('n.type_id, n.name, n.group_name, SUM(k.quantity) as killed')
            ->orderByDesc('killed')
            ->get()
            ->reject(fn ($r) => preg_match(NpcRegistry::NO_FACTION_ON_PURPOSE, (string) $r->name) || preg_match(NpcRegistry::NO_FACTION_ON_PURPOSE, (string) $r->group_name))
            ->take(25);

        if ($unknown->isEmpty()) {
            return ['label' => 'Unplaced NPCs', 'status' => 'ok', 'message' => 'Every rat killed in the last 30 days has a faction and a size.'];
        }

        return [
            'label' => 'Unplaced NPCs',
            'status' => 'warn',
            'message' => $unknown->count() === 1
                ? 'One NPC type killed in the last 30 days has no faction or size. NpcRegistry is where to place it.'
                : $unknown->count() . ' NPC types killed in the last 30 days have no faction or size. NpcRegistry is where to place them.',
            'details' => $unknown->mapWithKeys(fn ($r) => [
                ($r->name ? $r->name . ' (' . $r->type_id . ')' : 'Type ' . $r->type_id) => ($r->group_name ?? 'not in the SDE') . ', ' . $r->killed . ' killed',
            ])->all(),
        ];
    }

    private function npcsByMass(): array
    {
        $count = DB::table('ratting_manager_npc_types')->where('classified_by', 'mass')->count();

        return [
            'label' => 'NPCs sized by mass',
            'status' => 'info',
            'message' => $count === 0
                ? 'None: every size came from the group or type name.'
                : "{$count} NPC types were sized from their mass because their group mixes sizes. Worth a look if a size seems wrong.",
        ];
    }

    /**
     * Whether alerts and reports can reach anyone, and whether the last day's
     * sends went through.
     */
    public function notifications(): array
    {
        if (!Schema::hasTable('ratting_manager_webhooks')) {
            return ['label' => 'Notifications', 'status' => 'warn', 'message' => 'The notification tables are missing. The migrations have not run.'];
        }

        $webhooks = DB::table('ratting_manager_webhooks')->count();
        if ($webhooks === 0) {
            return ['label' => 'Notifications', 'status' => 'info', 'message' => 'No webhooks yet. Add one under Settings, Webhooks to get alerts and reports in Discord or Slack.'];
        }

        $nowhere = DB::table('ratting_manager_notification_types as t')
            ->where('t.enabled', true)
            ->whereNotExists(function ($q) {
                $q->from('ratting_manager_type_webhook as b')
                    ->join('ratting_manager_webhooks as w', 'w.id', '=', 'b.webhook_id')
                    ->whereColumn('b.type_id', 't.id')
                    ->where('b.enabled', true)
                    ->where('w.enabled', true);
            })
            ->pluck('t.type_key')
            ->map(fn ($k) => \RattingManager\Services\Notification\NotificationTypes::has($k) ? \RattingManager\Services\Notification\NotificationTypes::name($k) : $k)
            ->all();

        $since = now()->subDay();
        $failed = DB::table('ratting_manager_webhook_deliveries')->where('attempted_at', '>=', $since)->where('success', false)->count();
        $sent = DB::table('ratting_manager_webhook_deliveries')->where('attempted_at', '>=', $since)->count();

        if ($failed > 0) {
            $last = DB::table('ratting_manager_webhook_deliveries')->where('success', false)->orderByDesc('id')->first(['status_code', 'error_message']);

            return [
                'label' => 'Notifications',
                'status' => 'warn',
                'message' => "{$failed} of {$sent} sends in the last 24 hours did not go through. Last: " . mb_substr((string) ($last->error_message ?? 'HTTP ' . ($last->status_code ?? 0)), 0, 160) . ' Notification Testing has each webhook\'s history.',
            ];
        }

        $outsiders = DB::table('ratting_manager_notification_types')->where('type_key', 'outsiders')->value('enabled');
        if ($outsiders && !app(HomeSpace::class)->isSet()) {
            $name = \RattingManager\Services\Notification\NotificationTypes::name('outsiders');
            $nowhere = array_values(array_diff($nowhere, [$name]));
            $nowhere[] = $name . ' (no home space set)';
        }

        $message = "{$webhooks} webhook(s); {$sent} send(s) in the last 24 hours, all went through.";
        if ($nowhere !== []) {
            $message .= ' Switched on but sent nowhere: ' . implode(', ', $nowhere) . '.';
        }

        return ['label' => 'Notifications', 'status' => $nowhere === [] ? 'ok' : 'info', 'message' => $message];
    }

    /** ESI's hourly kill counts, which the Outsiders alert compares against. */
    public function systemKills(): array
    {
        if (!Schema::hasTable('ratting_manager_system_kills')) {
            return ['label' => 'Kill counts from ESI', 'status' => 'warn', 'message' => 'The table is missing. The migrations have not run.'];
        }

        $outsiders = DB::table('ratting_manager_notification_types')->where('type_key', 'outsiders')->value('enabled');
        $watched = count(app(HomeSpace::class)->systems());

        if ($watched === 0) {
            return [
                'label' => 'Kill counts from ESI',
                'status' => $outsiders ? 'warn' : 'info',
                'message' => $outsiders
                    ? 'The Outsiders alert is switched on but no home space is set, so it watches nothing and sends nothing. Set regions, constellations or systems under Settings, Notifications.'
                    : 'No home space is set, so none are kept. The Outsiders alert needs one.',
            ];
        }

        $last = DB::table('ratting_manager_system_kills')->max('updated_at');

        if ($last === null) {
            return [
                'label' => 'Kill counts from ESI',
                'status' => 'info',
                'message' => "None kept yet. ratting-manager:poll-system-kills keeps them hourly for the {$watched} system(s) in the home space; the Outsiders alert needs them.",
            ];
        }

        $stale = \Carbon\Carbon::parse($last)->lt(now()->subHours(3));

        return [
            'label' => 'Kill counts from ESI',
            'status' => $stale && $outsiders ? 'warn' : 'ok',
            'message' => 'Last kept ' . \Carbon\Carbon::parse($last)->diffForHumans() . ", for the {$watched} system(s) in the home space."
                . ($stale && $outsiders ? ' Check that SeAT\'s scheduler runs ratting-manager:poll-system-kills.' : ''),
        ];
    }
}
