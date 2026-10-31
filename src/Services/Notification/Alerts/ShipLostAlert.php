<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RattingManager\Services\Character\AffiliationResolutionService;
use RattingManager\Services\Notification\Message;
use RattingManager\Support\Format;

/**
 * A tracked corporation's pilot lost a ship in the system they were ratting
 * in. Killmails come from SeAT, which holds those its tokens can see.
 *
 * Killmails and journals both arrive with a lag, so a loss with no tick
 * beside it yet is looked at again on later runs, for two days.
 */
class ShipLostAlert extends Alert
{
    private const LOOK_BACK_HOURS = 48;

    /** EVE's Ship category. A loss in any other, a tractor unit or a structure, is not a ratting ship. */
    private const SHIP_CATEGORY = 6;

    public function type(): string
    {
        return 'ship_lost';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type()) || $this->corporations() === [] || !Schema::hasTable('killmail_victims')) {
            return 0;
        }

        $losses = DB::table('killmail_victims as v')
            ->join('killmail_details as d', 'd.killmail_id', '=', 'v.killmail_id')
            ->leftJoin('invTypes as it', 'it.typeID', '=', 'v.ship_type_id')
            ->leftJoin('invGroups as ig', 'ig.groupID', '=', 'it.groupID')
            ->whereIn('v.corporation_id', $this->corporations())
            ->whereNotNull('v.character_id')
            // A type the SDE does not have yet is kept, so a new hull is never missed.
            ->where(fn ($q) => $q->whereNull('ig.categoryID')->orWhere('ig.categoryID', self::SHIP_CATEGORY))
            ->where('d.killmail_time', '>=', Carbon::now()->subHours(self::LOOK_BACK_HOURS))
            ->orderBy('d.killmail_time')
            ->get(['v.killmail_id', 'v.character_id', 'v.corporation_id', 'v.ship_type_id', 'd.killmail_time', 'd.solar_system_id']);

        $window = max(1, (int) $this->settings->get('notifications.ship_lost_minutes'));
        $sent = 0;

        foreach ($losses as $loss) {
            $key = 'ship_lost:' . $loss->killmail_id;
            if ($this->state->isOpen($key)) {
                continue;
            }

            $time = Carbon::parse($loss->killmail_time);
            // A tick pays for the 20 minutes before it, so the one after the
            // loss can still be for ratting done before it.
            $tick = DB::table('ratting_manager_ticks')
                ->where('character_id', $loss->character_id)
                ->where('solar_system_id', $loss->solar_system_id)
                ->whereBetween('ticked_at', [$time->copy()->subMinutes($window), $time->copy()->addMinutes(20)])
                ->orderByDesc('ticked_at')
                ->first(['ticked_at', 'session_id']);

            if ($tick === null) {
                continue;
            }

            $session = $tick->session_id ? DB::table('ratting_manager_sessions')->where('id', $tick->session_id)->first(['started_at', 'minutes']) : null;
            $attackers = DB::table('killmail_attackers')->where('killmail_id', $loss->killmail_id)->get(['character_id', 'corporation_id', 'ship_type_id', 'final_blow']);
            $final = $attackers->firstWhere('final_blow', true);
            [$finalBlow, $finalCorp] = $final && $final->character_id ? $this->attacker($final) : [null, null];

            $result = $this->notifier->send($this->type(), (int) $loss->corporation_id, $this->message([
                'killmail_id' => (int) $loss->killmail_id,
                'pilot' => $this->players->name((int) $loss->character_id),
                'ship_type_id' => (int) $loss->ship_type_id,
                'ship' => $this->typeName((int) $loss->ship_type_id),
                'system_id' => (int) $loss->solar_system_id,
                'time' => $time,
                'tick' => Carbon::parse($tick->ticked_at),
                'session_minutes' => $session ? (int) $session->minutes : null,
                'attackers' => $attackers->count(),
                'final_blow' => $finalBlow,
                'final_corp' => $finalCorp,
                'final_ship' => $final && $final->ship_type_id ? $this->typeName((int) $final->ship_type_id) : null,
            ]));

            if ($this->done($result)) {
                $this->state->opened($key, ['character_id' => (int) $loss->character_id]);
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        $time = Carbon::now()->subMinutes(30);

        return $this->message([
            'killmail_id' => 1,
            'pilot' => 'Rykel Fairchilde',
            'ship_type_id' => 17738,
            'ship' => 'Machariel',
            'system_id' => 30003841,
            'time' => $time,
            'tick' => $time->copy()->subMinutes(6),
            'session_minutes' => 140,
            'attackers' => 7,
            'final_blow' => 'Some Hunter',
            'final_corp' => 'Some Corporation',
            'final_ship' => 'Sabre',
        ]);
    }

    private function message(array $l): Message
    {
        $gap = (int) round(abs($l['tick']->diffInSeconds($l['time'], false)) / 60);
        $tickLine = trans($l['tick']->lte($l['time']) ? 'ratting-manager::notifications.minutes_before' : 'ratting-manager::notifications.minutes_after', [
            'time' => $l['tick']->format('H:i'),
            'minutes' => $gap,
        ]);

        $final = $l['final_blow']
            ? $l['final_blow'] . ($l['final_corp'] ? ' (' . $l['final_corp'] . ')' : '') . ($l['final_ship'] ? ', ' . $l['final_ship'] : '')
            : trans('ratting-manager::notifications.npcs_only');

        $message = Message::forType($this->type(), trans('ratting-manager::notifications.lost_title', ['ship' => $l['ship']]))
            ->description(trans('ratting-manager::notifications.lost_body', [
                'pilot' => $l['pilot'],
                'ship' => $l['ship'],
                'system' => $this->systemLabel($l['system_id']),
            ]))
            ->field(trans('ratting-manager::notifications.when'), self::eveTime($l['time']) . ' ' . trans('ratting-manager::notifications.eve'))
            ->field(trans('ratting-manager::notifications.last_tick'), $tickLine);

        if ($l['session_minutes']) {
            $message->field(trans('ratting-manager::notifications.ratting_for'), Format::hours($l['session_minutes']));
        }

        return $message
            ->field(trans('ratting-manager::notifications.final_blow'), $final)
            ->field(trans('ratting-manager::notifications.attackers'), (string) $l['attackers'])
            ->url('https://zkillboard.com/kill/' . $l['killmail_id'] . '/')
            ->thumbnail('https://images.evetech.net/types/' . $l['ship_type_id'] . '/render?size=64')
            ->at($l['time']);
    }

    private function typeName(int $typeId): string
    {
        return DB::table('invTypes')->where('typeID', $typeId)->value('typeName') ?? 'Type ' . $typeId;
    }

    /**
     * The final blow's pilot and corporation by name. SeAT seldom knows a
     * hunter, so one it cannot name is looked up the way unknown ratters are:
     * ESI, then EVEWho and zKillboard when ESI cannot answer. What is found
     * is kept, so a hunter who comes back is named without asking again.
     *
     * @return array{0: string, 1: ?string} pilot, corporation
     */
    private function attacker(object $final): array
    {
        $lookups = app(AffiliationResolutionService::class);
        $id = (int) $final->character_id;
        $corporationId = (int) $final->corporation_id;

        $name = DB::table('character_infos')->where('character_id', $id)->value('name')
            ?: DB::table('universe_names')->where('entity_id', $id)->where('category', 'character')->value('name')
            ?: ($lookups->known([$id])[$id]->character_name ?? null);

        if (!$name) {
            $lookups->resolve([$id]);
            $name = $lookups->known([$id])[$id]->character_name ?? null;
        }

        // The corporation as of the kill, which the pilot may have left since.
        $corporation = $corporationId > 0
            ? ($lookups->lookUpCorporationName($corporationId) ?: '#' . $corporationId)
            : null;

        return [$name ?: '#' . $id, $corporation];
    }
}
