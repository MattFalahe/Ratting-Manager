<?php

namespace RattingManager\Services\Import;

/**
 * What a journal description says about who ratted and where.
 *
 * context_id carries the system on every row seen so far, so the system
 * here is only a fallback. The name is what matters: for a ratter SeAT holds
 * no token for, the description is the only place their name appears.
 */
final class JournalText
{
    private const BOUNTY = '/^(.+) got bounty prizes for killing pirates in (.+)$/u';
    private const ESS = '/^Encounter Surveillance System in (.+) transferred funds to (.+)$/u';

    /** @return array{name: ?string, system: ?string} */
    public static function read(?string $description): array
    {
        $description = trim((string) $description);

        if (preg_match(self::BOUNTY, $description, $m)) {
            return ['name' => trim($m[1]), 'system' => trim($m[2])];
        }

        if (preg_match(self::ESS, $description, $m)) {
            return ['name' => trim($m[2]), 'system' => trim($m[1])];
        }

        return ['name' => null, 'system' => null];
    }
}
