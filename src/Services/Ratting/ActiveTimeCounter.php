<?php

namespace RattingManager\Services\Ratting;

/**
 * ActiveTime::minutes() one tick at a time, for ticks read in time order.
 *
 * Holds two numbers instead of every timestamp, so a year of a big
 * corporation's ticks can be counted without keeping them all in memory.
 * The ticks must come oldest first; ActiveTime::minutes() sorts and then
 * counts with this, so both give the same answer.
 */
final class ActiveTimeCounter
{
    private int $seconds = 0;
    private ?int $coveredUntil = null;

    /** @param int $timestamp unix seconds, no earlier than the last one added */
    public function add(int $timestamp): void
    {
        $span = ActiveTime::TICK_MINUTES * 60;
        $start = $timestamp - $span;

        if ($this->coveredUntil === null || $start >= $this->coveredUntil) {
            $this->seconds += $span;
        } elseif ($timestamp > $this->coveredUntil) {
            $this->seconds += $timestamp - $this->coveredUntil;
        }

        $this->coveredUntil = max($this->coveredUntil ?? $timestamp, $timestamp);
    }

    public function minutes(): int
    {
        return intdiv($this->seconds + 30, 60);
    }
}
