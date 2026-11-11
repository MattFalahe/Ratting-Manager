<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Import\JournalImporter;
use RattingManager\Services\Npc\NpcClassifier;
use RattingManager\Services\Npc\SpawnDetector;

/**
 * Every step from journal rows to dashboard figures, in the order each one
 * needs the last: rows first, then the rates that need paired rows, then
 * everything built on the rows.
 */
class Pipeline
{
    private Settings $settings;
    private JournalImporter $importer;
    private TaxRates $taxRates;
    private NpcClassifier $npcs;
    private SpawnDetector $spawns;
    private SessionBuilder $sessions;
    private DailySummaryBuilder $summaries;
    private CharacterDayBuilder $characterDays;
    private MonthlySummaryBuilder $monthly;

    public function __construct(
        Settings $settings,
        JournalImporter $importer,
        TaxRates $taxRates,
        NpcClassifier $npcs,
        SpawnDetector $spawns,
        SessionBuilder $sessions,
        DailySummaryBuilder $summaries,
        CharacterDayBuilder $characterDays,
        MonthlySummaryBuilder $monthly
    ) {
        $this->settings = $settings;
        $this->importer = $importer;
        $this->taxRates = $taxRates;
        $this->npcs = $npcs;
        $this->spawns = $spawns;
        $this->sessions = $sessions;
        $this->summaries = $summaries;
        $this->characterDays = $characterDays;
        $this->monthly = $monthly;
    }

    /** How many steps run() reports, for a caller numbering them. */
    public const STEPS = 9;

    /**
     * @param Progress|callable|null $progress a Progress, or a callable given each step's name as it starts
     * @param bool $onlyChanged rebuild sessions and daily summaries only for
     *                          the characters and days this run's import
     *                          changed; false rebuilds the whole window
     */
    public function run(Carbon $since, $progress = null, bool $onlyChanged = true): array
    {
        $progress = $progress instanceof Progress ? $progress : new Progress($progress);
        $count = $progress->counting();
        $since = RawTicks::clamp($since);

        $corporations = $this->settings->configuredCorporations();
        $report = ['since' => $since->toDateTimeString(), 'corporations' => $corporations];

        if ($corporations === []) {
            $report['note'] = 'No corporation is configured. Pick one on the Settings page.';
            return $report;
        }

        $started = microtime(true);

        // Rows SeAT added since the last run can be from days back, when a
        // token has come back, so this run reaches back for them. What nothing
        // touches is not rebuilt, so this costs a wider read and no more.
        $lastRun = $this->settings->get('state.last_process_at');
        if ($onlyChanged && $lastRun !== null) {
            $oldest = $this->importer->oldestRowAddedSince(Carbon::parse($lastRun)->subMinutes(5));
            if ($oldest !== null && $oldest->lt($since)) {
                $since = RawTicks::clamp($oldest);
            }
        }

        // Every write to a tick or payout goes through Eloquent and moves its
        // updated_at on, so the rows touched can be read back after the import.
        // A run that stopped part way left its rows waiting, so this one reaches
        // back over what it owed as well as its own window.
        $owed = $this->owe($since);
        $since = $owed['since'];
        $touchedSince = $owed['touched_since'];
        $report['since'] = $since->toDateTimeString();

        $progress->step('Corporation journal', $count ? $this->importer->corporationRowCount($since) : 0);
        $report['corporation_journal'] = $this->importer->importCorporation($since, $progress);

        $progress->step('Character journals', $count ? $this->importer->characterRowCount($since) : 0);
        $report['character_journal'] = $this->importer->importCharacter($since, $progress);

        $progress->step('Tax rates', $count ? $this->taxRates->rowCount($corporations, $since) : 0);
        $report['tax_rates'] = [
            'snapshot' => $this->taxRates->snapshot($corporations),
            'learned_days' => $this->taxRates->learn($corporations, $since, $progress),
        ];
        $report['estimates'] = $this->taxRates->estimate($corporations, $since, $progress);

        $progress->step('NPC types', 1);
        $report['npc_types'] = $this->npcs->sync();
        $reclassified = $this->npcs->reclassifyIfRulesChanged();
        if ($reclassified !== null) {
            $report['npc_types_reclassified'] = $reclassified;
        }
        $progress->advance();

        // New rules can change which rats count, back to the first tick, so
        // the whole list is gone through once instead of the usual window.
        $progress->step('Special spawns', 1);
        if ($reclassified !== null) {
            $rebuilt = $this->spawns->rebuild();
            $report['special_spawns'] = $rebuilt['found'];
            $report['special_spawns_removed'] = $rebuilt['removed'];
        } else {
            $report['special_spawns'] = $this->spawns->detect($since);
        }
        $progress->advance();

        $changed = $onlyChanged ? $this->changed($since, $touchedSince) : null;

        $gap = $this->sessions->gapMinutes();
        if ($changed !== null && $gap !== (int) $this->settings->get('state.session_gap_built')) {
            // The gap changed since the sessions on file were built, so every
            // character in the window is grouped again under the new one.
            $sessionCharacters = null;
        } else {
            $sessionCharacters = $changed['characters'] ?? null;
        }

        $report['changed'] = $changed === null ? 'all' : [
            'characters' => count($changed['characters']),
            'days' => count($changed['dates']),
        ];

        $progress->step('Sessions', $count ? $this->sessions->characterCount($since, $sessionCharacters) : 0);
        $report['sessions'] = $this->sessions->rebuild($since, $progress, $sessionCharacters);
        $this->settings->set('state.session_gap_built', $gap);

        if ($changed === null) {
            $progress->step('Daily summaries', $count ? DailySummaryBuilder::dayCount($since) : 0);
            $report['daily'] = $this->summaries->rebuild($since, null, $progress);
        } else {
            $progress->step('Daily summaries', $count ? count($changed['dates']) : 0);
            $report['daily'] = $this->summaries->rebuildDays($changed['dates'], $progress);
        }

        // The first run after these tables arrive fills them from every tick
        // and day on file. That changes nothing already there, and the pages
        // read the ticks and daily rows until it is done.
        $filled = (bool) $this->settings->get('state.summaries_built');
        $dates = $filled ? ($changed['dates'] ?? CharacterDayBuilder::datesFrom($since)) : CharacterDayBuilder::allDates();
        $months = $filled ? MonthlySummaryBuilder::monthsOf($dates) : MonthlySummaryBuilder::allMonths();

        $progress->step('Character days', count($dates));
        $report['character_days'] = $this->characterDays->rebuildDays($dates, $progress);

        $progress->step('Monthly summaries', count($months));
        $report['monthly'] = $this->monthly->rebuildMonths($months, $progress);

        if (!$filled) {
            $this->settings->set('state.summaries_built', true);
        }

        $report['seconds'] = round(microtime(true) - $started, 1);

        $this->settings->set('state.last_process_at', now()->toDateTimeString());
        $this->settings->set('state.last_process_report', $report);
        $this->settings->set('state.build_owed', null);

        return $report;
    }

    /**
     * Note that rows written from now on still need their sessions and
     * summaries built, before any are written. Without this a run that dies
     * after its import leaves those days behind for good: the next run only
     * sees what it touched itself. It stays noted until a run gets to the end,
     * and a note already there is only ever widened. One second earlier so a
     * row written in the same second is not missed.
     *
     * @return array{since: Carbon, touched_since: Carbon} what the next run has to cover
     */
    public function owe(Carbon $since): array
    {
        $since = RawTicks::clamp($since);
        $touchedSince = Carbon::now()->subSecond();
        $owed = $this->settings->get('state.build_owed');

        if (is_array($owed)) {
            $since = RawTicks::clamp(Carbon::parse($owed['since'])->min($since));
            $touchedSince = Carbon::parse($owed['touched_since'])->min($touchedSince);
        }

        $this->settings->set('state.build_owed', [
            'since' => $since->toDateTimeString(),
            'touched_since' => $touchedSince->toDateTimeString(),
        ]);

        return ['since' => $since, 'touched_since' => $touchedSince];
    }

    /**
     * Whether a run's report shows anything the dashboards are built from
     * was written: ticks or payouts, special spawns, or NPC types
     * classified again. A run that only read rows it already had changes
     * none of the figures.
     */
    public static function changedAnything(array $report): bool
    {
        if (isset($report['note'])) {
            return false;
        }

        $changed = $report['changed'] ?? 'all';
        if (!is_array($changed) || $changed['characters'] > 0 || $changed['days'] > 0) {
            return true;
        }

        return ($report['special_spawns'] ?? 0) > 0
            || ($report['special_spawns_removed'] ?? 0) > 0
            || isset($report['npc_types_reclassified']);
    }

    /**
     * The characters whose ticks and the days whose ticks or payouts were
     * written since $touchedSince, within the window.
     *
     * Payouts only matter to the daily summaries; sessions are built from
     * ticks alone.
     *
     * @return array{characters: int[], dates: string[]}
     */
    public function changed(Carbon $since, Carbon $touchedSince): array
    {
        $characters = [];
        $dates = [];

        foreach ([['ratting_manager_ticks', 'ticked_at'], ['ratting_manager_ess_payouts', 'paid_at']] as [$table, $column]) {
            $rows = DB::table($table)
                ->where($column, '>=', $since)
                ->where('updated_at', '>=', $touchedSince)
                ->selectRaw("character_id, DATE({$column}) as day")
                ->distinct()
                ->get();

            foreach ($rows as $row) {
                if ($table === 'ratting_manager_ticks') {
                    $characters[(int) $row->character_id] = true;
                }
                $dates[(string) $row->day] = true;
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        return ['characters' => array_keys($characters), 'dates' => $dates];
    }
}
