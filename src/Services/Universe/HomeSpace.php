<?php

namespace RattingManager\Services\Universe;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;

/**
 * The space the Outsiders alert watches: the regions, constellations and
 * systems an admin listed under Notifications. Nothing listed means nothing
 * is watched, so the alert stays quiet until someone says where home is,
 * rather than watching every system a ratter has passed through.
 */
class HomeSpace
{
    /** kind => [setting, SeAT table, ID column] */
    public const KINDS = [
        'regions' => ['notifications.home_regions', 'regions', 'region_id'],
        'constellations' => ['notifications.home_constellations', 'constellations', 'constellation_id'],
        'systems' => ['notifications.home_systems', 'solar_systems', 'system_id'],
    ];

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function isSet(): bool
    {
        foreach (self::KINDS as [$key]) {
            if ($this->settings->get($key) !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return int[] every system in the listed regions and constellations, and the systems listed */
    public function systems(): array
    {
        if (!$this->isSet()) {
            return [];
        }

        $regions = $this->settings->get('notifications.home_regions');
        $constellations = $this->settings->get('notifications.home_constellations');
        $systems = $this->settings->get('notifications.home_systems');

        return DB::table('solar_systems')
            ->where(function ($q) use ($regions, $constellations, $systems) {
                $q->whereIn('region_id', $regions ?: [0])
                    ->orWhereIn('constellation_id', $constellations ?: [0])
                    ->orWhereIn('system_id', $systems ?: [0]);
            })
            ->orderBy('system_id')
            ->pluck('system_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** What is listed for one kind, by name, as the form shows it. */
    public function names(string $kind): string
    {
        [$key, $table, $column] = self::KINDS[$kind];
        $ids = $this->settings->get($key);
        if ($ids === []) {
            return '';
        }

        $names = DB::table($table)->whereIn($column, $ids)->pluck('name', $column);

        return implode(', ', array_map(fn ($id) => $names[$id] ?? (string) $id, $ids));
    }

    /**
     * Names or IDs as typed, separated by commas, as the IDs SeAT knows,
     * with whatever it does not know so the admin can be told. Region and
     * system names can hold spaces ("The Forge", "New Caldari"), so spaces
     * do not separate.
     *
     * @return array{0: int[], 1: string[]} IDs in the order typed, and the entries not found
     */
    public function parse(string $kind, ?string $input): array
    {
        [, $table, $column] = self::KINDS[$kind];
        $tokens = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $input)), 'strlen')));
        if ($tokens === []) {
            return [[], []];
        }

        $numbers = array_values(array_filter($tokens, 'ctype_digit'));
        $words = array_values(array_diff($tokens, $numbers));

        $byId = $numbers === [] ? [] : DB::table($table)->whereIn($column, $numbers)->pluck($column)->map(fn ($id) => (int) $id)->flip()->all();
        // SeAT's collation compares names without case, so the lookup is
        // keyed on lower case to match what came back to what was typed.
        $byName = [];
        if ($words !== []) {
            foreach (DB::table($table)->whereIn('name', $words)->get([$column, 'name']) as $row) {
                $byName[mb_strtolower($row->name)] = (int) $row->{$column};
            }
        }

        $ids = [];
        $unknown = [];
        foreach ($tokens as $token) {
            $id = ctype_digit($token) ? (isset($byId[(int) $token]) ? (int) $token : null) : ($byName[mb_strtolower($token)] ?? null);
            if ($id === null) {
                $unknown[] = $token;
            } elseif (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return [$ids, $unknown];
    }
}
