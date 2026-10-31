<?php

namespace RattingManager\Services\Configuration;

/**
 * Every setting the plugin keeps, with its scope, type and default.
 *
 * Mining Manager learned the hard way that a key read in two places with two
 * defaults ends up holding two values. Here a key and its default are written
 * once, and anything not listed cannot be saved.
 *
 * - INSTALL: one value for the whole install, kept with no corporation.
 * - STATE: the plugin's own records, such as when the last import ran. Never
 *   on the settings page.
 */
final class SettingsRegistry
{
    public const INSTALL = 'install';
    public const STATE = 'state';

    /** @var array<string, array{0: string, 1: string, 2: mixed}> key => [scope, type, default] */
    private const KEYS = [
        // Which corporations are tracked. Ticks from any other corporation are
        // never imported.
        'general.configured_corporations' => [self::INSTALL, 'int_list', []],

        // One missed tick should not split a session, so the gap allows a
        // little over two tick lengths.
        'data.session_gap_minutes' => [self::INSTALL, 'int', 45],
        'data.gone_quiet_days' => [self::INSTALL, 'int', 14],
        'data.regular_ratter_days' => [self::INSTALL, 'int', 90],
        // How long ticks, kill rows, ESS payouts and daily kill totals are kept
        // once the summaries hold their days. 0 keeps them for the year the
        // pages can show: going below that takes away rebuilding those days,
        // so an operator has to choose it.
        'data.raw_tick_days' => [self::INSTALL, 'int', 0],

        // Close the ratting pages to anyone with none of their characters in a
        // tracked corporation now. Off by default: a role alone decides until
        // an admin chooses this, and a SeAT squad can do the same job.
        'access.members_only' => [self::INSTALL, 'bool', false],

        'spawns.watch_type_ids' => [self::INSTALL, 'int_list', []],

        'leaderboard.size' => [self::INSTALL, 'int', 25],

        // Special spawns: this rank and above, capitals, and the watch list.
        // A spawn older than the age limit when it is first imported is not
        // sent, so a late import of old journal rows does not flood channels.
        'notifications.spawn_min_rank' => [self::INSTALL, 'string', 'clone'],
        'notifications.spawn_capitals' => [self::INSTALL, 'bool', true],
        'notifications.spawn_max_age_hours' => [self::INSTALL, 'int', 24],

        // A corporation journal with no new row for this long has stopped
        // coming in: SeAT pulls it about hourly, and a corporation that rats
        // has rows most hours.
        'notifications.stalled_hours' => [self::INSTALL, 'int', 12],

        // Outsiders: NPC kills in an hour, beyond the tracked corporations'
        // own, that count as someone else ratting there, and the home space
        // it watches: every system in the regions and constellations listed,
        // and the systems listed. With none listed it watches nothing.
        'notifications.outsider_min_kills' => [self::INSTALL, 'int', 100],
        'notifications.home_regions' => [self::INSTALL, 'int_list', []],
        'notifications.home_constellations' => [self::INSTALL, 'int_list', []],
        'notifications.home_systems' => [self::INSTALL, 'int_list', []],

        // A loss counts as a ratting ship lost when the pilot had a tick in
        // the same system this long before it.
        'notifications.ship_lost_minutes' => [self::INSTALL, 'int', 60],

        // How many players each ranking in the reports lists.
        'notifications.rankings_size' => [self::INSTALL, 'int', 10],

        'state.last_process_at' => [self::STATE, 'string', null],
        'state.last_process_report' => [self::STATE, 'json', null],
        // Set while rows have been written that no finished run has built on
        // yet: from when, and over which days the next run has to cover them.
        'state.build_owed' => [self::STATE, 'json', null],
        'state.initialized_at' => [self::STATE, 'string', null],
        // NpcRegistry::RULES the NPC types on file were classified under.
        'state.npc_rules' => [self::STATE, 'int', 0],
        // The session gap the stored sessions were built with, so the
        // scheduled run knows to rebuild every character when it changes.
        'state.session_gap_built' => [self::STATE, 'int', 0],
        // Whether the character days and monthly summaries have been filled
        // from what was on file, so the pages can read them.
        'state.summaries_built' => [self::STATE, 'bool', false],
        // The first day ticks are still held for. Nothing rebuilds or imports
        // a day before it. Empty until the first drop.
        'state.raw_kept_from' => [self::STATE, 'string', null],
        'state.last_raw_drop' => [self::STATE, 'json', null],
        'state.last_year_drop' => [self::STATE, 'json', null],
        // The newest row of the corporation journal the last import saw.
        'state.corporation_journal_seen' => [self::STATE, 'int', 0],
        'state.last_raw_restore' => [self::STATE, 'json', null],
    ];

    public static function has(string $key): bool
    {
        return isset(self::KEYS[$key]);
    }

    public static function scope(string $key): string
    {
        return self::KEYS[$key][0];
    }

    public static function type(string $key): string
    {
        return self::KEYS[$key][1];
    }

    public static function default(string $key)
    {
        return self::KEYS[$key][2];
    }

    /** @return string[] */
    public static function keys(?string $scope = null): array
    {
        return array_keys(array_filter(
            self::KEYS,
            fn ($entry) => $scope === null || $entry[0] === $scope
        ));
    }
}
