<?php

namespace RattingManager\Services\Notification\Alerts;

use RattingManager\Services\Notification\Message;
use RattingManager\Services\Notification\Reports\RattingReports;

/**
 * The alerts in the order a run checks them, and a sample post for every
 * notification type for the Test buttons.
 */
final class Alerts
{
    public const CLASSES = [
        'data_stalled' => DataStalledAlert::class,
        'special_spawn' => SpawnAlert::class,
        'ship_lost' => ShipLostAlert::class,
        'outsiders' => OutsidersAlert::class,
        'untracked_ratter' => UntrackedAlert::class,
        'wallet_lost' => WalletLostAlert::class,
        'gone_quiet' => GoneQuietAlert::class,
    ];

    public static function make(string $type): Alert
    {
        return app(self::CLASSES[$type]);
    }

    public static function sample(string $type): Message
    {
        if (isset(self::CLASSES[$type])) {
            return self::make($type)->sample();
        }

        return app(RattingReports::class)->sample($type);
    }
}
