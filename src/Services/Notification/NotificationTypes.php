<?php

namespace RattingManager\Services\Notification;

use Illuminate\Support\Facades\DB;

/**
 * Every notification the plugin can send. The table rows hold only what an
 * admin can change (the master switch and the default role); names and
 * descriptions are in the language file, and the rest is here.
 */
final class NotificationTypes
{
    public const ALERTS = 'alerts';
    public const REPORTS = 'reports';

    /** key => [group, embed colour, Font Awesome icon] */
    private const TYPES = [
        'special_spawn' => [self::ALERTS, 0xC98500, 'fa-star'],
        'ship_lost' => [self::ALERTS, 0xD95926, 'fa-skull'],
        'outsiders' => [self::ALERTS, 0xD95926, 'fa-user-secret'],
        'data_stalled' => [self::ALERTS, 0xDC3545, 'fa-exclamation-triangle'],
        'untracked_ratter' => [self::ALERTS, 0x3987E5, 'fa-eye-slash'],
        'wallet_lost' => [self::ALERTS, 0xC98500, 'fa-key'],
        'gone_quiet' => [self::ALERTS, 0x6C757D, 'fa-bed'],
        'director_report_weekly' => [self::REPORTS, 0x667EEA, 'fa-file-invoice-dollar'],
        'director_report_monthly' => [self::REPORTS, 0x667EEA, 'fa-file-invoice-dollar'],
        'rankings_weekly' => [self::REPORTS, 0x199E70, 'fa-trophy'],
        'rankings_monthly' => [self::REPORTS, 0x199E70, 'fa-trophy'],
    ];

    /** @return string[] */
    public static function keys(?string $group = null): array
    {
        return array_keys(array_filter(self::TYPES, fn ($t) => $group === null || $t[0] === $group));
    }

    public static function has(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    public static function group(string $key): string
    {
        return self::TYPES[$key][0];
    }

    public static function colour(string $key): int
    {
        return self::TYPES[$key][1];
    }

    public static function icon(string $key): string
    {
        return self::TYPES[$key][2];
    }

    public static function name(string $key): string
    {
        return trans('ratting-manager::notifications.type_' . $key);
    }

    public static function description(string $key): string
    {
        return trans('ratting-manager::notifications.type_' . $key . '_hint');
    }

    /**
     * Adds a row for any type the table does not have yet, switched on and
     * bound to nothing, so a type added in an update shows up in Settings
     * without sending anywhere until an admin binds it.
     */
    public static function ensureRows(): void
    {
        $have = DB::table('ratting_manager_notification_types')->pluck('type_key')->all();
        $missing = array_diff(self::keys(), $have);

        if ($missing === []) {
            return;
        }

        $now = now();
        DB::table('ratting_manager_notification_types')->insertOrIgnore(array_map(fn ($key) => [
            'type_key' => $key, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
        ], array_values($missing)));
    }
}
