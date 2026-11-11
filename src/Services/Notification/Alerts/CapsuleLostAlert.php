<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Notification\Message;

/**
 * A tracked corporation's pilot lost their capsule in the system they were
 * ratting in. It comes on its own, so a channel can carry ships lost without
 * every pod that follows them, or the pods somewhere else.
 */
class CapsuleLostAlert extends ShipLostAlert
{
    /** How long before the capsule a ship lost in the same system still counts as the one it flew out of. */
    private const SHIP_BEFORE_MINUTES = 30;

    public function type(): string
    {
        return 'capsule_lost';
    }

    protected function losses(Builder $query): Builder
    {
        return $query->where('it.groupID', self::CAPSULE_GROUP);
    }

    protected function details(object $loss, Carbon $time): array
    {
        // Ships only, as Ship lost takes them: a pilot often loses a tractor
        // unit or a depot in the same fight, and that is not what they flew.
        $ship = $this->ships(DB::table('killmail_victims as v')
            ->join('killmail_details as d', 'd.killmail_id', '=', 'v.killmail_id')
            ->leftJoin('invTypes as it', 'it.typeID', '=', 'v.ship_type_id')
            ->leftJoin('invGroups as ig', 'ig.groupID', '=', 'it.groupID')
            ->where('v.character_id', $loss->character_id)
            ->where('d.solar_system_id', $loss->solar_system_id)
            ->whereBetween('d.killmail_time', [$time->copy()->subMinutes(self::SHIP_BEFORE_MINUTES), $time])
            ->where('v.killmail_id', '<>', $loss->killmail_id))
            ->orderByDesc('d.killmail_time')
            ->first(['v.ship_type_id', 'd.killmail_time']);

        return [
            'ship_before' => $ship ? $this->typeName((int) $ship->ship_type_id) : null,
            'ship_before_minutes' => $ship ? (int) round(Carbon::parse($ship->killmail_time)->diffInSeconds($time) / 60) : null,
        ];
    }

    protected function headline(array $l): Message
    {
        $body = $l['ship_before'] !== null
            ? trans('ratting-manager::notifications.' . ($l['ship_before_minutes'] > 0 ? 'capsule_body_after_ship' : 'capsule_body_right_after_ship'), [
                'pilot' => $l['pilot'],
                'system' => $this->systemLabel($l['system_id']),
                'ship' => $l['ship_before'],
                'minutes' => $l['ship_before_minutes'],
            ])
            : trans('ratting-manager::notifications.capsule_body', [
                'pilot' => $l['pilot'],
                'system' => $this->systemLabel($l['system_id']),
            ]);

        return Message::forType($this->type(), trans('ratting-manager::notifications.capsule_title', ['pilot' => $l['pilot']]))
            ->description($body);
    }

    public function sample(): Message
    {
        $time = Carbon::now()->subMinutes(29);

        return $this->message([
            'killmail_id' => 1,
            'pilot' => 'Rykel Fairchilde',
            'ship_type_id' => 670,
            'ship' => 'Capsule',
            'ship_before' => 'Machariel',
            'ship_before_minutes' => 1,
            'system_id' => 30003841,
            'time' => $time,
            'tick' => $time->copy()->subMinutes(7),
            'session_minutes' => 140,
            'attackers' => 3,
            'final_blow' => 'Some Hunter',
            'final_corp' => 'Some Corporation',
            'final_ship' => 'Sabre',
        ]);
    }
}
