<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Import\JournalText;

class JournalTextTest extends TestCase
{
    public function test_a_bounty_row_names_the_ratter_and_the_system(): void
    {
        $this->assertSame(
            ['name' => 'Rykel Fairchilde', 'system' => 'C-J6MT'],
            JournalText::read('Rykel Fairchilde got bounty prizes for killing pirates in C-J6MT')
        );
    }

    public function test_an_ess_row_names_them_the_other_way_round(): void
    {
        $this->assertSame(
            ['name' => 'Sineryn Trantus', 'system' => '4-EP12'],
            JournalText::read('Encounter Surveillance System in 4-EP12 transferred funds to Sineryn Trantus')
        );
    }

    public function test_a_name_with_accents_and_a_quote(): void
    {
        $this->assertSame('Élan O\'Neill', JournalText::read('Élan O\'Neill got bounty prizes for killing pirates in Jita')['name']);
    }

    public function test_anything_else_says_nothing(): void
    {
        $nothing = ['name' => null, 'system' => null];

        $this->assertSame($nothing, JournalText::read(null));
        $this->assertSame($nothing, JournalText::read(''));
        $this->assertSame($nothing, JournalText::read('Market transaction'));
    }
}
