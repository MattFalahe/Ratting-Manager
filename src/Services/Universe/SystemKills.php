<?php

namespace RattingManager\Services\Universe;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NPC, ship and pod kills per system from ESI's public system_kills, kept
 * hourly for the systems being watched, so ratting by outsiders can be told
 * from the tracked corporations' own.
 */
class SystemKills
{
    private const USER_AGENT = 'SeAT Ratting Manager (https://github.com/MattFalahe/Ratting-Manager)';

    /** Kept long enough for Diagnostics to show a month. */
    public const KEEP_DAYS = 30;

    /**
     * Fetches the last hour and keeps the watched systems' rows. ESI counts
     * the hour up to its Last-Modified time, so the row is filed under the
     * hour that started an hour before it.
     *
     * @param int[] $systems
     * @return array{ok: bool, hour: ?string, stored: int, message: string}
     */
    public function poll(array $systems): array
    {
        if ($systems === []) {
            return ['ok' => true, 'hour' => null, 'stored' => 0, 'message' => 'No home space is set for the Outsiders alert, so there are no systems to watch.'];
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
                ->get(rtrim((string) config('ratting-manager.esi_base_url'), '/') . '/universe/system_kills/', ['datasource' => 'tranquility']);
        } catch (\Throwable $e) {
            Log::info('Ratting Manager: ESI did not answer system_kills: ' . $e->getMessage());

            return ['ok' => false, 'hour' => null, 'stored' => 0, 'message' => 'ESI did not answer: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            return ['ok' => false, 'hour' => null, 'stored' => 0, 'message' => 'ESI answered HTTP ' . $response->status()];
        }

        $modified = $response->header('Last-Modified');
        $end = $modified ? Carbon::parse($modified)->utc() : Carbon::now('UTC');
        $hour = $end->copy()->subHour()->startOfHour();
        if ($end->minute >= 30) {
            $hour->addHour();
        }

        $watched = array_flip($systems);
        $now = Carbon::now();
        $rows = [];
        foreach ((array) $response->json() as $row) {
            $id = (int) ($row['system_id'] ?? 0);
            if (!isset($watched[$id])) {
                continue;
            }
            $rows[] = [
                'solar_system_id' => $id,
                'hour' => $hour->toDateTimeString(),
                'npc_kills' => (int) ($row['npc_kills'] ?? 0),
                'ship_kills' => (int) ($row['ship_kills'] ?? 0),
                'pod_kills' => (int) ($row['pod_kills'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ratting_manager_system_kills')->upsert($chunk, ['solar_system_id', 'hour'], ['npc_kills', 'ship_kills', 'pod_kills', 'updated_at']);
        }

        DB::table('ratting_manager_system_kills')->where('hour', '<', Carbon::now()->subDays(self::KEEP_DAYS))->delete();

        return ['ok' => true, 'hour' => $hour->toDateTimeString(), 'stored' => count($rows), 'message' => sprintf('Kept %d of %d watched systems for %s.', count($rows), count($systems), $hour->format('Y-m-d H:00'))];
    }
}
