<?php

namespace RattingManager\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RattingManager\Models\EssPayout;
use RattingManager\Models\Tick;
use RattingManager\Services\Character\AffiliationResolutionService;
use RattingManager\Services\Character\CorporationOnDate;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Ratting\Progress;
use RattingManager\Services\Ratting\RawTicks;
use RattingManager\Services\Universe\SolarSystems;
use Throwable;

/**
 * Brings bounty ticks and ESS payouts in from SeAT's wallet journals.
 *
 * The corporation journal holds the corp's cut of every member's tick and
 * names the ratter in second_party_id, so it sees members who never added a
 * wallet token. The character journal holds what the character received. The
 * same tick shows up in both at the same second, and whichever arrives first
 * creates the row the other pairs onto. Rows are keyed on their journal ids,
 * so reading the same days again changes nothing.
 */
class JournalImporter
{
    private const STREAMS = [
        'bounty' => [
            'ref_types' => ['bounty_prizes', 'bounty_prize'],
            'model' => Tick::class,
            'time' => 'ticked_at',
        ],
        'ess' => [
            'ref_types' => ['ess_escrow_transfer'],
            'model' => EssPayout::class,
            'time' => 'paid_at',
        ],
    ];

    /**
     * Paired rows have shared the same second on every install checked. The
     * small allowance is there in case a payout is ever written a moment
     * apart; a character's ticks are minutes apart, so it cannot pick the
     * wrong one.
     */
    public const PAIR_TOLERANCE_SECONDS = 10;

    private const COLUMNS = [
        'id', 'date', 'ref_type', 'second_party_id', 'amount', 'tax', 'tax_receiver_id', 'reason',
        'context_id', 'context_id_type', 'description',
    ];

    /** ESI serves 30 days of journal, so a row arriving late is never older than this. */
    private const LATE_ROW_DAYS = 31;

    private Settings $settings;
    private CorporationOnDate $corporations;
    private KillRecorder $kills;
    private SolarSystems $systems;
    private AffiliationResolutionService $lookups;

    /** @var array<int, string> character_id => name read from a description */
    private array $names = [];

    public function __construct(
        Settings $settings,
        CorporationOnDate $corporations,
        KillRecorder $kills,
        SolarSystems $systems,
        AffiliationResolutionService $lookups
    ) {
        $this->settings = $settings;
        $this->corporations = $corporations;
        $this->kills = $kills;
        $this->systems = $systems;
        $this->lookups = $lookups;
    }

    /** How many rows importCorporation() will read, for a progress bar. */
    public function corporationRowCount(Carbon $since): int
    {
        $corporations = $this->settings->configuredCorporations();

        return $corporations === [] ? 0 : DB::table('corporation_wallet_journals')
            ->whereIn('corporation_id', $corporations)
            ->whereIn('ref_type', self::refTypes())
            ->where('date', '>=', $since)
            ->count();
    }

    /** How many rows importCharacter() will read, for a progress bar. */
    public function characterRowCount(Carbon $since): int
    {
        $corporations = $this->settings->configuredCorporations();
        if ($corporations === []) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($this->candidateCharacters($corporations), 1000) as $characters) {
            $total += DB::table('character_wallet_journals')
                ->whereIn('character_id', $characters)
                ->whereIn('ref_type', self::refTypes())
                ->where('date', '>=', $since)
                ->count();
        }

        return $total;
    }

    public function importCorporation(Carbon $since, ?Progress $progress = null): array
    {
        $since = RawTicks::clamp($since);
        $progress ??= Progress::none();
        $corporations = $this->settings->configuredCorporations();
        $report = $this->emptyReport();

        if ($corporations === []) {
            $report['note'] = 'No corporation is configured.';
            return $report;
        }

        foreach (self::STREAMS as $stream => $definition) {
            $inWindow = fn () => DB::table('corporation_wallet_journals')
                ->whereIn('corporation_id', $corporations)
                ->whereIn('ref_type', $definition['ref_types'])
                ->where('date', '>=', $since);

            // Paging by id with only a date to go on, the database starts at the
            // first id SeAT holds and walks years of rows to reach the window.
            // Starting at the window's own first id takes that walk away.
            $firstId = $inWindow()->min('id');
            if ($firstId === null) {
                continue;
            }

            $inWindow()
                ->where('id', '>=', $firstId)
                ->select(array_merge(['corporation_id'], self::COLUMNS))
                ->chunkById(1000, function ($rows) use ($stream, $corporations, &$report, $progress) {
                    $this->applyChunk('corporation', $stream, $rows, $corporations, $report);
                    $progress->advance(count($rows));
                }, 'id');
        }

        $this->storeNames();

        return $report;
    }

    public function importCharacter(Carbon $since, ?Progress $progress = null): array
    {
        $since = RawTicks::clamp($since);
        $progress ??= Progress::none();
        $corporations = $this->settings->configuredCorporations();
        $report = $this->emptyReport();

        if ($corporations === []) {
            $report['note'] = 'No corporation is configured.';
            return $report;
        }

        foreach (array_chunk($this->candidateCharacters($corporations), 1000) as $characters) {
            $this->corporations->load($characters);

            foreach (self::STREAMS as $stream => $definition) {
                DB::table('character_wallet_journals')
                    ->whereIn('character_id', $characters)
                    ->whereIn('ref_type', $definition['ref_types'])
                    ->where('date', '>=', $since)
                    ->select(array_merge(['character_id'], self::COLUMNS))
                    ->chunkById(1000, function ($rows) use ($stream, $corporations, &$report, $progress) {
                        $this->applyChunk('character', $stream, $rows, $corporations, $report);
                        $progress->advance(count($rows));
                    }, 'id');
            }
        }

        $this->storeNames();

        return $report;
    }

    /**
     * The oldest day of any row SeAT has added to either journal since a time.
     *
     * A token that comes back brings up to 30 days of journal with it at once.
     * Without this those days wait for the nightly wide read; with it the next
     * half-hourly run reaches back for them.
     */
    public function oldestRowAddedSince(Carbon $added): ?Carbon
    {
        $corporations = $this->settings->configuredCorporations();
        if ($corporations === []) {
            return null;
        }

        $window = now()->subDays(self::LATE_ROW_DAYS)->startOfDay();
        $query = DB::table('corporation_wallet_journals')
            ->whereIn('corporation_id', $corporations)
            ->whereIn('ref_type', self::refTypes())
            ->where('date', '>=', $window);

        // SeAT has no index on when a row was added, so asking by time reads the
        // whole month's rows of every corporation on the server, twice an hour.
        // Its row number only grows, so that goes straight to what is new.
        $numbered = Schema::hasColumn('corporation_wallet_journals', 'internal_id');
        $newest = $numbered ? (int) DB::table('corporation_wallet_journals')->max('internal_id') : 0;
        $seen = (int) $this->settings->get('state.corporation_journal_seen');

        // Numbers lower than the last seen mean the table has been rebuilt.
        if ($numbered && $seen > 0 && $seen <= $newest) {
            $query->where('internal_id', '>', $seen);
        } else {
            $query->where('created_at', '>=', $added);
        }
        $oldest = $query->min('date');

        if ($numbered) {
            $this->settings->set('state.corporation_journal_seen', $newest);
        }

        foreach (array_chunk($this->candidateCharacters($corporations), 1000) as $characters) {
            $own = DB::table('character_wallet_journals')
                ->whereIn('character_id', $characters)
                ->whereIn('ref_type', self::refTypes())
                ->where('date', '>=', $window)
                ->where('created_at', '>=', $added)
                ->min('date');

            if ($own !== null && ($oldest === null || $own < $oldest)) {
                $oldest = $own;
            }
        }

        return $oldest === null ? null : Carbon::parse($oldest)->startOfDay();
    }

    /** @return string[] every ref type either stream reads */
    private static function refTypes(): array
    {
        return array_merge(...array_values(array_column(self::STREAMS, 'ref_types')));
    }

    /**
     * Everyone who has been in a configured corporation at some point. Their
     * corporation on the day decides whether a row counts.
     *
     * @param int[] $corporations
     * @return int[]
     */
    private function candidateCharacters(array $corporations): array
    {
        return DB::table('character_affiliations')
            ->whereIn('corporation_id', $corporations)
            ->pluck('character_id')
            ->merge(
                DB::table('character_corporation_histories')
                    ->whereIn('corporation_id', $corporations)
                    ->pluck('character_id')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function applyChunk(string $side, string $stream, Collection $rows, array $corporations, array &$report): void
    {
        $items = [];
        foreach ($rows as $row) {
            $report[$stream]['read']++;

            try {
                $item = $this->normalise($side, $stream, $row, $corporations);
            } catch (Throwable $e) {
                $this->rowFailed($side, $stream, (int) $row->id, $e, $report);
                continue;
            }

            if (is_string($item)) {
                $report[$stream]['skipped'][$item] = ($report[$stream]['skipped'][$item] ?? 0) + 1;
                continue;
            }

            $items[] = $item;
        }

        if ($items === []) {
            return;
        }

        try {
            $this->writeItems($items, $side, $stream, $report);
        } catch (Throwable $e) {
            // SeAT's rows come from outside, so one the database will not take
            // must not cost the rows around it, or every later run stops on
            // the same row until it leaves the window. Going again a row at a
            // time leaves out only the ones that really cannot be written.
            foreach ($items as $item) {
                try {
                    $this->writeItems([$item], $side, $stream, $report);
                } catch (Throwable $e) {
                    $this->rowFailed($side, $stream, $item['journal_id'], $e, $report);
                }
            }
        }
    }

    private function rowFailed(string $side, string $stream, int $journalId, Throwable $e, array &$report): void
    {
        $report[$stream]['failed']++;
        Log::warning("Ratting Manager: left out {$side} journal row {$journalId} ({$stream}): " . mb_substr($e->getMessage(), 0, 300));
    }

    /** Writes these rows together or not at all; the report only counts them once they are in. */
    private function writeItems(array $items, string $side, string $stream, array &$report): void
    {
        $definition = self::STREAMS[$stream];
        /** @var class-string<Tick|EssPayout> $model */
        $model = $definition['model'];
        $timeColumn = $definition['time'];
        $ownColumn = $side . '_journal_id';

        $existing = $model::whereIn($ownColumn, array_column($items, 'journal_id'))
            ->get()
            ->keyBy($ownColumn);

        $times = array_map(fn ($item) => strtotime($item['time']), $items);
        $candidates = $model::whereIn('character_id', array_unique(array_column($items, 'character_id')))
            ->whereBetween($timeColumn, [
                date('Y-m-d H:i:s', min($times) - self::PAIR_TOLERANCE_SECONDS),
                date('Y-m-d H:i:s', max($times) + self::PAIR_TOLERANCE_SECONDS),
            ])
            ->whereNull($ownColumn)
            ->get()
            ->groupBy('character_id');

        $counts = ['created' => 0, 'paired' => 0, 'updated' => 0];

        DB::transaction(function () use ($items, $existing, &$candidates, $model, $side, $stream, $timeColumn, $ownColumn, &$counts) {
            $isBounty = $stream === 'bounty';
            // Rows not seen before are written together at the end of the
            // chunk, with their kill group already on them, instead of one
            // INSERT each and a second UPDATE for the kills.
            $new = [];
            $newKills = [];
            $killRows = [];

            foreach ($items as $item) {
                $record = $existing->get($item['journal_id']);

                if ($record) {
                    $this->fill($record, $side, $item, false);
                    $updated = $record->isDirty();
                    $kills = $isBounty ? $this->kills->prepare($record, $item['reason']) : [];
                    if ($record->isDirty()) {
                        $record->save();
                    }
                    if ($updated) {
                        $counts['updated']++;
                    }
                } elseif ($record = $this->takeMatch($candidates, $item, $timeColumn)) {
                    $record->{$ownColumn} = $item['journal_id'];
                    $this->fill($record, $side, $item, true);
                    $kills = $isBounty ? $this->kills->prepare($record, $item['reason']) : [];
                    $record->save();
                    $counts['paired']++;
                } else {
                    $record = new $model([
                        'character_id' => $item['character_id'],
                        'corporation_id' => $item['corporation_id'],
                        'solar_system_id' => $item['system_id'],
                        $timeColumn => $item['time'],
                        'kind' => $side,
                        $ownColumn => $item['journal_id'],
                    ]);
                    $this->fill($record, $side, $item, false);
                    if ($isBounty) {
                        $kills = $this->kills->prepare($record, $item['reason']);
                        if ($kills !== []) {
                            $newKills[$item['journal_id']] = $kills;
                        }
                    }
                    $new[] = $record;
                    $counts['created']++;
                    continue;
                }

                if ($kills !== []) {
                    array_push($killRows, ...$this->kills->rows((int) $record->id, $kills));
                }
            }

            if ($new !== []) {
                $now = (new $model())->freshTimestampString();
                $table = (new $model())->getTable();
                $rows = array_map(function ($record) use ($now) {
                    return $record->getAttributes() + ['created_at' => $now, 'updated_at' => $now];
                }, $new);

                // Every row of a chunk carries the same columns, but a
                // tick with no kill list has no kill_group or npc_kills set,
                // so the rows are given one shape before the batch insert.
                $columns = array_keys(array_merge(...$rows));
                $defaults = array_fill_keys($columns, null);
                $rows = array_map(fn ($row) => array_replace($defaults, $row), $rows);
                if (in_array('npc_kills', $columns, true)) {
                    foreach ($rows as &$row) {
                        $row['npc_kills'] ??= 0;
                    }
                    unset($row);
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                if ($newKills !== []) {
                    $ids = DB::table($table)
                        ->whereIn($ownColumn, array_keys($newKills))
                        ->pluck('id', $ownColumn);
                    foreach ($newKills as $journalId => $kills) {
                        array_push($killRows, ...$this->kills->rows((int) $ids[$journalId], $kills));
                    }
                }
            }

            foreach (array_chunk($killRows, 1000) as $chunk) {
                DB::table('ratting_manager_tick_kills')->insertOrIgnore($chunk);
            }
        });

        foreach ($counts as $name => $count) {
            $report[$stream][$name] += $count;
        }
    }

    /** @return array<string, mixed>|string the row ready to apply, or why it was left out */
    private function normalise(string $side, string $stream, $row, array $corporations)
    {
        $characterId = $side === 'corporation' ? (int) $row->second_party_id : (int) $row->character_id;
        if ($characterId <= 0) {
            return 'no_character';
        }

        if ((float) $row->amount <= 0) {
            return 'not_a_payment';
        }

        $text = JournalText::read($row->description);
        $systemId = ($row->context_id_type === 'system_id' && $row->context_id)
            ? (int) $row->context_id
            : $this->systems->idForName($text['system']);

        if ($stream === 'bounty' && !$systemId) {
            return 'no_system';
        }

        $time = substr((string) $row->date, 0, 19);

        if ($side === 'corporation') {
            $corporationId = (int) $row->corporation_id;
        } else {
            // A taxed row names the corporation that took the tax, the one they
            // were in when it paid, however far behind SeAT's history is.
            $corporationId = (int) ($row->tax_receiver_id ?? 0) ?: $this->corporations->at($characterId, $time);
            if (!$corporationId || !in_array($corporationId, $corporations, true)) {
                return 'other_corporation';
            }
        }

        if ($text['name'] !== null) {
            $this->names[$characterId] = $text['name'];
        }

        return [
            'journal_id' => (int) $row->id,
            'character_id' => $characterId,
            'corporation_id' => $corporationId,
            'system_id' => $systemId,
            'time' => $time,
            'amount' => round((float) $row->amount, 2),
            'tax' => round((float) ($row->tax ?? 0), 2),
            'reason' => $row->reason,
        ];
    }

    /**
     * Put one journal's side of the figures on a row. The corporation row is
     * the authority on which corporation the tick belongs to; the character
     * row on what the character received.
     */
    private function fill($record, string $side, array $item, bool $pairing): void
    {
        if ($side === 'corporation') {
            $record->corp_tax_amount = $item['amount'];
            $record->corporation_id = $item['corporation_id'];
            if ($record->kind === 'corporation' || !$record->exists) {
                // Worked out once the corporation's tax rate for the day is known.
                $record->net_is_estimated = true;
            }
        } else {
            $record->net_amount = $item['amount'];
            $record->net_is_estimated = false;
            $record->tax_rate = null;
            // A taxed bounty row says what the corporation took, to the ISK, so
            // the cut is known while the corporation's journal is not coming
            // in. Its own row takes over when it arrives.
            if ($record->corporation_journal_id === null && $item['tax'] > 0) {
                $record->corp_tax_amount = $item['tax'];
            }
        }

        if ($pairing) {
            $record->kind = 'paired';
        }
    }

    private function takeMatch(Collection &$candidates, array $item, string $timeColumn)
    {
        $pool = $candidates->get($item['character_id']);
        if (!$pool) {
            return null;
        }

        $target = strtotime($item['time']);
        $bestKey = null;
        $bestGap = null;

        foreach ($pool as $key => $candidate) {
            if ((int) $candidate->solar_system_id !== (int) $item['system_id']) {
                continue;
            }

            $gap = abs($candidate->{$timeColumn}->getTimestamp() - $target);
            if ($gap <= self::PAIR_TOLERANCE_SECONDS && ($bestGap === null || $gap < $bestGap)) {
                $bestKey = $key;
                $bestGap = $gap;
            }
        }

        if ($bestKey === null) {
            return null;
        }

        $match = $pool->get($bestKey);
        $candidates->put($item['character_id'], $pool->forget($bestKey));

        return $match;
    }

    /**
     * Hand the names descriptions gave to the character lookups, so untracked
     * ratters are named on every page without asking ESI.
     */
    private function storeNames(): void
    {
        $this->lookups->rememberJournalNames($this->names);
        $this->names = [];
    }

    private function emptyReport(): array
    {
        $stream = ['read' => 0, 'created' => 0, 'paired' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => []];

        return ['bounty' => $stream, 'ess' => $stream];
    }
}
