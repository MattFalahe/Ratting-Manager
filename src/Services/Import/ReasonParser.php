<?php

namespace RattingManager\Services\Import;

/**
 * Reads the kill list CCP puts in a bounty row's reason: "60580: 2,60317: 2".
 *
 * A long list can arrive cut short with a trailing "...". The pairs before it
 * are still right, so they are kept and the rest is simply not known.
 */
final class ReasonParser
{
    /** @return array<int, int> type_id => quantity, sorted by type_id */
    public static function kills(?string $reason): array
    {
        if ($reason === null || $reason === '') {
            return [];
        }

        preg_match_all('/(\d+)\s*:\s*(\d+)/', $reason, $matches, PREG_SET_ORDER);

        $kills = [];
        foreach ($matches as [, $typeId, $quantity]) {
            $typeId = (int) $typeId;
            $quantity = (int) $quantity;
            if ($typeId > 0 && $quantity > 0) {
                $kills[$typeId] = ($kills[$typeId] ?? 0) + $quantity;
            }
        }

        ksort($kills);

        return $kills;
    }

    /**
     * Fleet members who shared a payout each get a row with the same time,
     * system and kills. This is the key that tells them apart from separate
     * ratters, so their rats are counted once.
     *
     * @param array<int, int> $kills
     */
    public static function killGroup(string $time, ?int $systemId, array $kills): ?string
    {
        if ($kills === []) {
            return null;
        }

        $list = [];
        foreach ($kills as $typeId => $quantity) {
            $list[] = $typeId . ':' . $quantity;
        }

        return sha1($time . '|' . (int) $systemId . '|' . implode(',', $list));
    }
}
