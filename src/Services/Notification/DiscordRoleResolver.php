<?php

namespace RattingManager\Services\Notification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Finds the Discord roles other SeAT plugins already know, for the role
 * pickers in Settings. The same lookup Structure Manager uses.
 *
 * Providers are found by their tables, since plugins are installed in
 * different ways and a class check can miss one that is there:
 *
 *   - discord_roles: SeAT Broadcast's curated roles, with colours
 *   - seat_connector_sets: warlof/seat-connector, rows with connector_type
 *     discord are the guild's roles
 *   - warlof_discord_connector_roles / discord_connector_roles: the older
 *     warlof Discord connector
 *
 * Roles found in more than one are listed once, by their Discord ID, with
 * the richest source's details. With no provider the pickers fall back to
 * typing a role ID.
 */
class DiscordRoleResolver
{
    public const PROVIDER_DISCORD_ROLES_TABLE = 'discord-roles-table';
    public const PROVIDER_SEAT_CONNECTOR = 'seat-connector';
    public const PROVIDER_WARLOF_DISCORD = 'warlof-discord';

    /** @return string[] richest first */
    public static function providers(): array
    {
        $providers = [];

        if (Schema::hasTable('discord_roles') && Schema::hasColumn('discord_roles', 'role_id')) {
            $providers[] = self::PROVIDER_DISCORD_ROLES_TABLE;
        }
        if (Schema::hasTable('seat_connector_sets')) {
            $providers[] = self::PROVIDER_SEAT_CONNECTOR;
        }
        foreach (['warlof_discord_connector_roles', 'discord_connector_roles'] as $table) {
            if (Schema::hasTable($table)) {
                $providers[] = self::PROVIDER_WARLOF_DISCORD;
                break;
            }
        }

        return $providers;
    }

    public static function providerLabel(string $provider): string
    {
        return match ($provider) {
            self::PROVIDER_DISCORD_ROLES_TABLE => 'SeAT Broadcast',
            self::PROVIDER_SEAT_CONNECTOR => 'SeAT Connector',
            self::PROVIDER_WARLOF_DISCORD => 'Warlof Discord',
            default => $provider,
        };
    }

    /**
     * Every role, once each, sorted by name.
     *
     * @return array<int, array{id: string, name: string, mention_format: string, color: ?string, source: string, sources: string[]}>
     */
    public static function roles(): array
    {
        $merged = [];

        foreach (self::providers() as $provider) {
            try {
                $rows = match ($provider) {
                    self::PROVIDER_DISCORD_ROLES_TABLE => self::fromDiscordRoles(),
                    self::PROVIDER_SEAT_CONNECTOR => self::fromSeatConnector(),
                    self::PROVIDER_WARLOF_DISCORD => self::fromWarlof(),
                    default => [],
                };
            } catch (\Throwable $e) {
                Log::warning('Ratting Manager: could not read Discord roles from ' . $provider . ': ' . $e->getMessage());
                continue;
            }

            foreach ($rows as $row) {
                $id = $row['id'];
                if ($id === '') {
                    continue;
                }
                if (!isset($merged[$id])) {
                    $merged[$id] = $row + ['source' => $provider, 'sources' => [$provider]];
                } else {
                    $merged[$id]['sources'][] = $provider;
                    $merged[$id]['color'] ??= $row['color'];
                }
            }
        }

        $roles = array_values($merged);
        usort($roles, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $roles;
    }

    /** @return array<string, array> roles by Discord ID */
    public static function lookup(): array
    {
        return array_column(self::roles(), null, 'id');
    }

    /**
     * A stored mention described for a page: whether it is a role, a user,
     * "none" or something that would be dropped, and the role's name and
     * colour when a provider knows it.
     */
    public static function describe(?string $raw, ?array $lookup = null): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (WebhookDispatcher::isNoMention($raw)) {
            return ['kind' => 'none', 'raw' => $raw, 'id' => null, 'name' => null, 'color' => null, 'known' => false];
        }

        $kind = 'unknown';
        if (preg_match('/^<@&\d+>$/', $raw) || preg_match('/^\d+$/', $raw)) {
            $kind = 'role';
        } elseif (preg_match('/^<@!?\d+>$/', $raw)) {
            $kind = 'user';
        }

        $id = preg_match('/(\d{2,})/', $raw, $m) ? $m[1] : null;
        $lookup ??= self::lookup();
        $role = $kind === 'role' && $id !== null ? ($lookup[$id] ?? null) : null;

        return [
            'kind' => $kind,
            'raw' => $raw,
            'id' => $id,
            'name' => $role['name'] ?? null,
            'color' => $role['color'] ?? null,
            'known' => $role !== null,
        ];
    }

    private static function fromDiscordRoles(): array
    {
        return DB::table('discord_roles')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'role_id', 'mention_format', 'color'])
            ->map(fn ($r) => [
                'id' => (string) $r->role_id,
                'name' => (string) $r->name,
                'mention_format' => (string) ($r->mention_format ?: '<@&' . $r->role_id . '>'),
                'color' => $r->color ?: null,
            ])->all();
    }

    private static function fromSeatConnector(): array
    {
        return DB::table('seat_connector_sets')
            ->where('connector_type', 'discord')
            ->orderBy('name')
            ->get(['connector_id', 'name'])
            ->map(fn ($r) => [
                'id' => (string) $r->connector_id,
                'name' => (string) $r->name,
                'mention_format' => '<@&' . $r->connector_id . '>',
                'color' => null,
            ])->all();
    }

    private static function fromWarlof(): array
    {
        foreach (['warlof_discord_connector_roles', 'discord_connector_roles'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $idColumn = in_array('discord_id', $columns, true) ? 'discord_id' : 'id';
            $nameColumn = in_array('name', $columns, true) ? 'name' : $idColumn;

            return DB::table($table)
                ->orderBy($nameColumn)
                ->get([$idColumn . ' as role_id', $nameColumn . ' as name'])
                ->map(fn ($r) => [
                    'id' => (string) $r->role_id,
                    'name' => (string) $r->name,
                    'mention_format' => '<@&' . $r->role_id . '>',
                    'color' => null,
                ])->all();
        }

        return [];
    }
}
