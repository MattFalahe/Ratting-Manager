<?php

namespace RattingManager\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

/**
 * Ratting Manager's schedules.
 *
 * AbstractScheduleSeeder only adds rows that are missing, so an operator's
 * own changes to these are kept on every update. A schedule that has to
 * change goes in getDeprecatedSchedules() and comes back as a new entry.
 */
class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        return [
            // SeAT pulls wallet journals about hourly; reading twice an hour
            // keeps a new tick no more than half an hour behind SeAT.
            [
                'command' => 'ratting-manager:process',
                'expression' => '10,40 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // A member who adds a wallet token brings 30 days of journal with
            // them. The daily wide read pairs those days with the corporation
            // rows already imported, which the half-hourly two-day read cannot
            // reach.
            [
                'command' => 'ratting-manager:process --days=31',
                'expression' => '25 4 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // Ratters SeAT does not know are looked up in the background, so no
            // page waits on ESI: the ones pages asked for every minute, the
            // rest with answers due another look every 10 minutes. Same
            // pattern as Mining Manager.
            [
                'command' => 'ratting-manager:resolve-characters --requested',
                'expression' => '* * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'ratting-manager:resolve-characters',
                'expression' => '*/10 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // Alerts, five minutes after each import so they see its ticks.
            [
                'command' => 'ratting-manager:notify',
                'expression' => '15,45 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // ESI updates its kill counts once an hour.
            [
                'command' => 'ratting-manager:poll-system-kills',
                'expression' => '7 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // Daily, late enough in the EVE morning that the last day of the
            // week or month is imported. Each post goes once per period.
            [
                'command' => 'ratting-manager:send-reports',
                'expression' => '20 11 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // When ratters who have moved left their tracked corporation.
            [
                'command' => 'ratting-manager:track-departures',
                'expression' => '37 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            // After the nightly 31-day read. Does nothing unless Keep raw
            // ticks is set.
            [
                'command' => 'ratting-manager:drop-old-ticks',
                'expression' => '55 4 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
