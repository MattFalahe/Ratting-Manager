<?php

namespace RattingManager\Services\Npc;

use Illuminate\Support\Facades\DB;
use RattingManager\Services\Configuration\Settings;

class NpcClassifier
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Classify every NPC type a tick has paid for that is not on file yet.
     * A type on file keeps its classification until the rules change.
     */
    public function sync(): int
    {
        // Read apart, since asking the index for its types needs no kill row read.
        $missing = array_values(array_diff(
            DB::table('ratting_manager_tick_kills')->distinct()->pluck('npc_type_id')->map(fn ($id) => (int) $id)->all(),
            DB::table('ratting_manager_npc_types')->pluck('type_id')->map(fn ($id) => (int) $id)->all()
        ));

        $written = 0;
        foreach (array_chunk($missing, 500) as $chunk) {
            $sde = DB::table('invTypes as t')
                ->leftJoin('invGroups as g', 'g.groupID', '=', 't.groupID')
                ->whereIn('t.typeID', $chunk)
                ->get(['t.typeID', 't.typeName', 't.mass', 'g.groupID', 'g.groupName'])
                ->keyBy('typeID');

            $now = now();
            $rows = [];
            foreach ($chunk as $typeId) {
                $type = $sde->get($typeId);
                $rows[] = array_merge(
                    [
                        'type_id' => $typeId,
                        'name' => $type->typeName ?? null,
                        'group_id' => isset($type->groupID) ? (int) $type->groupID : null,
                        'group_name' => $type->groupName ?? null,
                        'mass' => isset($type->mass) ? (float) $type->mass : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $this->classify(
                        $typeId,
                        $type->typeName ?? null,
                        isset($type->groupID) ? (int) $type->groupID : null,
                        $type->groupName ?? null,
                        isset($type->mass) ? (float) $type->mass : null
                    )
                );
            }

            DB::table('ratting_manager_npc_types')->insertOrIgnore($rows);
            $written += count($rows);
        }

        return $written;
    }

    /**
     * When the rules have changed since the types on file were classified,
     * classify every one of them again from what was kept of it. Returns how
     * many changed, or null when the rules have not changed.
     */
    public function reclassifyIfRulesChanged(): ?int
    {
        if ((int) $this->settings->get('state.npc_rules') === NpcRegistry::RULES) {
            return null;
        }

        $changed = 0;
        DB::table('ratting_manager_npc_types')->orderBy('type_id')->chunk(500, function ($types) use (&$changed) {
            foreach ($types as $type) {
                $now = $this->classify(
                    (int) $type->type_id,
                    $type->name,
                    $type->group_id === null ? null : (int) $type->group_id,
                    $type->group_name,
                    $type->mass === null ? null : (float) $type->mass
                );

                if ($now['faction'] !== $type->faction || $now['size'] !== $type->size || $now['rank'] !== $type->rank || $now['classified_by'] !== $type->classified_by) {
                    DB::table('ratting_manager_npc_types')->where('type_id', $type->type_id)->update($now + ['updated_at' => now()]);
                    $changed++;
                }
            }
        });

        $this->settings->set('state.npc_rules', NpcRegistry::RULES);

        return $changed;
    }

    /** @return array{faction: string, size: string, rank: string, classified_by: string} */
    public function classify(int $typeId, ?string $name, ?int $groupId, ?string $groupName, ?float $mass): array
    {
        if (isset(NpcRegistry::TYPES[$typeId])) {
            [$faction, $size, $rank] = NpcRegistry::TYPES[$typeId];

            return ['faction' => $faction, 'size' => $size, 'rank' => $rank, 'classified_by' => 'registry'];
        }

        if ($name === null && $groupName === null) {
            return ['faction' => 'other', 'size' => 'unknown', 'rank' => 'regular', 'classified_by' => 'unknown'];
        }

        $faction = NpcRegistry::GROUP_FACTIONS[$groupId] ?? $this->match(NpcRegistry::FACTION_PATTERNS, $groupName, $name) ?? 'other';
        $rank = $this->match(NpcRegistry::RANK_GROUP_PATTERNS, $groupName)
            ?? $this->match(NpcRegistry::RANK_NAME_PATTERNS, $name)
            ?? 'regular';

        // Mass before the type's own name: an Angel Carrier is a hauler, however
        // it is named. The name is only for a type with no mass to go on.
        $size = $this->match(NpcRegistry::SIZE_PATTERNS, $groupName);
        $by = 'group';

        if ($size === null) {
            $size = $this->sizeFromMass($mass);
            $by = 'mass';

            if ($size === 'unknown') {
                $size = $this->match(NpcRegistry::SIZE_PATTERNS, $name) ?? 'unknown';
                $by = $size === 'unknown' ? 'unknown' : 'group';
            }
        }

        return ['faction' => $faction, 'size' => $size, 'rank' => $rank, 'classified_by' => $by];
    }

    private function match(array $patterns, ?string ...$texts): ?string
    {
        foreach ($texts as $text) {
            if ($text === null || $text === '') {
                continue;
            }
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $text)) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function sizeFromMass(?float $mass): string
    {
        if (!$mass || $mass <= 0) {
            return 'unknown';
        }

        foreach (NpcRegistry::MASS_BANDS as $size => $limit) {
            if ($mass < $limit) {
                return $size;
            }
        }

        return 'capital';
    }
}
