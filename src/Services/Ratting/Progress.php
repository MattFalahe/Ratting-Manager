<?php

namespace RattingManager\Services\Ratting;

/**
 * How a long run tells whoever started it how far along it is. Each step
 * says what it is and how many things it will work through, then advances
 * as it goes. The initialize command draws progress bars from it; the
 * scheduled run only prints the step names, and nobody else listens.
 */
final class Progress
{
    /** @var callable|null fn (string $name, int $total) */
    private $onStep;

    /** @var callable|null fn (int $by) */
    private $onAdvance;

    public function __construct(?callable $onStep = null, ?callable $onAdvance = null)
    {
        $this->onStep = $onStep;
        $this->onAdvance = $onAdvance;
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Whether anyone draws the advances. Working out a step's total can cost
     * a query, so a run nobody watches skips it.
     */
    public function counting(): bool
    {
        return $this->onAdvance !== null;
    }

    public function step(string $name, int $total = 0): void
    {
        if ($this->onStep) {
            ($this->onStep)($name, $total);
        }
    }

    public function advance(int $by = 1): void
    {
        if ($this->onAdvance && $by > 0) {
            ($this->onAdvance)($by);
        }
    }
}
