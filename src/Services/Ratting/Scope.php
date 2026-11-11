<?php

namespace RattingManager\Services\Ratting;

/**
 * Whose ratting a figure covers: one or more corporations, and optionally
 * only some characters within them (a member's own, or one player's).
 */
final class Scope
{
    /** @var int[] */
    public array $corporations;

    /** @var int[]|null null for every character in the corporations */
    public ?array $characters;

    /**
     * @param int[] $corporations
     * @param int[]|null $characters
     */
    public function __construct(array $corporations, ?array $characters = null)
    {
        $this->corporations = array_values(array_unique(array_map('intval', $corporations)));
        $this->characters = $characters === null ? null : array_values(array_unique(array_map('intval', $characters)));
    }

    /** Narrow a query on a table with corporation_id and character_id columns. */
    public function apply($query, string $alias = '')
    {
        $prefix = $alias === '' ? '' : $alias . '.';
        $query->whereIn($prefix . 'corporation_id', $this->corporations ?: [0]);

        if ($this->characters !== null) {
            $query->whereIn($prefix . 'character_id', $this->characters ?: [0]);
        }

        return $query;
    }

    public function cacheKey(): string
    {
        return md5(json_encode([$this->corporations, $this->characters]));
    }
}
