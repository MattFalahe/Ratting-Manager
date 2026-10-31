<?php

namespace RattingManager\Services\Universe;

use Illuminate\Support\Facades\DB;

class SolarSystems
{
    /** @var array<string, ?int> */
    private array $byName = [];

    /** @var array<int, array{name: string, security: float}> */
    private array $byId = [];

    public function idForName(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (!array_key_exists($name, $this->byName)) {
            $id = DB::table('solar_systems')->where('name', $name)->value('system_id');
            $this->byName[$name] = $id ? (int) $id : null;
        }

        return $this->byName[$name];
    }

    /**
     * @param int[] $ids
     * @return array<int, array{name: string, security: float}>
     */
    public function details(array $ids): array
    {
        $missing = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys($this->byId)));

        foreach (array_chunk($missing, 1000) as $chunk) {
            foreach ($chunk as $id) {
                $this->byId[$id] = ['name' => 'System #' . $id, 'security' => 0.0];
            }

            $rows = DB::table('solar_systems')->whereIn('system_id', $chunk)->get(['system_id', 'name', 'security']);
            foreach ($rows as $row) {
                $this->byId[(int) $row->system_id] = ['name' => $row->name, 'security' => (float) $row->security];
            }
        }

        return array_intersect_key($this->byId, array_flip(array_map('intval', $ids)));
    }

    public function name(?int $id): string
    {
        if (!$id) {
            return '-';
        }

        return $this->details([$id])[$id]['name'];
    }
}
