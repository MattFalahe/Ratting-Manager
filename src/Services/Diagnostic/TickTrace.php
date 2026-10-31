<?php

namespace RattingManager\Services\Diagnostic;

use Illuminate\Support\Facades\DB;
use RattingManager\Models\EssPayout;
use RattingManager\Models\Tick;
use RattingManager\Services\Character\CorporationOnDate;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Import\ReasonParser;
use RattingManager\Services\Ratting\RawTicks;
use RattingManager\Services\Ratting\TaxRates;
use RattingManager\Services\Universe\SolarSystems;
use RattingManager\Support\Format;

/**
 * Follows one journal row through everything the plugin does with it, for
 * the Tick Trace tab and the trace-tick command.
 */
class TickTrace
{
    private Settings $settings;
    private Players $players;
    private SolarSystems $systems;
    private CorporationOnDate $corporations;
    private TaxRates $taxRates;

    public function __construct(
        Settings $settings,
        Players $players,
        SolarSystems $systems,
        CorporationOnDate $corporations,
        TaxRates $taxRates
    ) {
        $this->settings = $settings;
        $this->players = $players;
        $this->systems = $systems;
        $this->corporations = $corporations;
        $this->taxRates = $taxRates;
    }

    /**
     * @return array<int, array{title: string, status: string, rows: array<int, array{0: string, 1: string}>}>
     */
    public function trace(int $journalId): array
    {
        $sections = [];

        $corpRow = DB::table('corporation_wallet_journals')->where('id', $journalId)->first();
        $charRow = DB::table('character_wallet_journals')->where('id', $journalId)->first();
        $row = $corpRow ?? $charRow;

        if (!$row) {
            return [[
                'title' => 'Journal row',
                'status' => 'warn',
                'rows' => [['Result', "No corporation or character journal row has id {$journalId}."]],
            ]];
        }

        $side = $corpRow ? 'corporation' : 'character';
        $characterId = $side === 'corporation' ? (int) $row->second_party_id : (int) $row->character_id;
        $systemId = $row->context_id_type === 'system_id' ? (int) $row->context_id : null;

        $sections[] = [
            'title' => 'Journal row',
            'status' => 'info',
            'rows' => [
                ['Journal', $side === 'corporation' ? 'Corporation journal (the corporation\'s cut)' : 'Character journal (what the character received)'],
                ['Type', $row->ref_type],
                ['Date', (string) $row->date],
                ['Amount', Format::isk($row->amount)],
                ['Ratter', $this->players->name($characterId) . ' (' . $characterId . ')'],
                ['System', $systemId ? $this->systems->name($systemId) . ' (' . $systemId . ')' : 'not given'],
                ['Kill list', $row->reason ?: 'none'],
                ['Description', (string) $row->description],
            ],
        ];

        $corporationId = $side === 'corporation'
            ? (int) $row->corporation_id
            : $this->corporations->at($characterId, $row->date);
        $configured = in_array($corporationId, $this->settings->configuredCorporations(), true);

        $sections[] = [
            'title' => 'Corporation',
            'status' => $configured ? 'ok' : 'warn',
            'rows' => [
                ['Corporation at the time', $corporationId ? (string) $corporationId : 'unknown'],
                ['Tracked', $configured ? 'Yes, it is a configured corporation' : 'No, so this row is not imported'],
            ],
        ];

        $isBounty = in_array($row->ref_type, ['bounty_prizes', 'bounty_prize'], true);
        $model = $isBounty ? Tick::class : EssPayout::class;
        $record = $model::where($side . '_journal_id', $journalId)->first();

        $kept = RawTicks::keptFrom();
        if (!$record && $isBounty && $kept !== null && $row->date < $kept->toDateTimeString()) {
            $sections[] = [
                'title' => 'Imported row',
                'status' => 'info',
                'rows' => [['Result', 'Its tick has been dropped: ticks before ' . $kept->toDateString() . ' are only kept in the summaries now.']],
            ];

            return $sections;
        }

        if (!$record) {
            $sections[] = [
                'title' => 'Imported row',
                'status' => 'warn',
                'rows' => [['Result', 'Not imported yet. The next scheduled run reads the last two days; older rows come in with the initial import.']],
            ];

            return $sections;
        }

        $rows = [
            ['Kind', ucfirst($record->kind)],
            ['Corporation journal id', (string) ($record->corporation_journal_id ?? 'not seen')],
            ['Character journal id', (string) ($record->character_journal_id ?? 'not seen')],
            ['Net to the character', Format::isk($record->net_amount) . ($record->net_is_estimated ? ' (worked out from the cut)' : '')],
            ['Corporation cut', $record->corp_tax_amount === null ? 'not seen' : Format::isk($record->corp_tax_amount)],
        ];

        $time = $isBounty ? $record->ticked_at : $record->paid_at;
        $rate = $this->taxRates->rateFor($record->corporation_id, $time);
        if ($record->net_is_estimated && $record->tax_rate) {
            $rows[] = ['Sum', sprintf(
                '%s x (1 - %.4f) / %.4f = %s',
                number_format((float) $record->corp_tax_amount, 2),
                $record->tax_rate,
                $record->tax_rate,
                Format::isk($record->net_amount)
            )];
        } elseif ($record->kind === 'paired' && $record->corp_tax_amount > 0) {
            $rows[] = ['Rate on this row', sprintf('%.4f', $record->corp_tax_amount / ($record->corp_tax_amount + $record->net_amount))];
        }
        $rows[] = ['Corporation rate that day', $rate === null ? 'not known yet' : sprintf('%.2f%%', $rate * 100)];

        $sections[] = ['title' => 'Imported row', 'status' => 'ok', 'rows' => $rows];

        if ($isBounty) {
            $kills = ReasonParser::kills($row->reason);
            $names = DB::table('ratting_manager_npc_types')->whereIn('type_id', array_keys($kills))->get()->keyBy('type_id');
            $killRows = [];
            foreach ($kills as $typeId => $quantity) {
                $type = $names->get($typeId);
                $killRows[] = [
                    $type->name ?? ('Type ' . $typeId),
                    $quantity . ' x ' . ($type ? "{$type->faction}, {$type->size}, {$type->rank} (by {$type->classified_by})" : 'not classified yet'),
                ];
            }

            $sharing = $record->kill_group
                ? Tick::where('kill_group', $record->kill_group)->where('id', '!=', $record->id)->pluck('character_id')->all()
                : [];

            $killRows[] = ['Shared with', $sharing === []
                ? 'Nobody: a solo tick'
                : implode(', ', array_map(fn ($id) => $this->players->name((int) $id), $sharing)) . ' (fleet payout, rats counted once)'];

            $sections[] = ['title' => 'Kills', 'status' => 'info', 'rows' => $killRows];

            if ($record->session_id) {
                $session = DB::table('ratting_manager_sessions')->find($record->session_id);
                $sections[] = [
                    'title' => 'Session',
                    'status' => 'info',
                    'rows' => [
                        ['From', (string) $session->started_at],
                        ['To', (string) $session->ended_at],
                        ['Active', Format::hours((int) $session->minutes)],
                        ['Ticks', (string) $session->tick_count],
                        ['Bounty ISK per hour', Format::isk($session->isk_per_hour)],
                    ],
                ];
            }
        }

        return $sections;
    }
}
