<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Ratting\TaxRates;

class WorkedOutIncomeTest extends TestCase
{
    public function test_the_example_the_docs_give(): void
    {
        $this->assertSame(8_500_000.0, TaxRates::netFromCut(1_500_000.0, 0.15));
    }

    /** Real rows from a paired tick: the cut says what the character received, to the cent or two. */
    public function test_a_real_tick(): void
    {
        $this->assertEqualsWithDelta(9_517_522.89, TaxRates::netFromCut(1_679_562.86, 0.15), 0.05);
    }

    public function test_other_rates(): void
    {
        $this->assertSame(900_000.0, TaxRates::netFromCut(100_000.0, 0.10));
        $this->assertSame(100_000.0, TaxRates::netFromCut(100_000.0, 0.50));
    }
}
