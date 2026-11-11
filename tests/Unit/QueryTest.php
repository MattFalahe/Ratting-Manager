<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Support\Query;

class QueryTest extends TestCase
{
    private function request(array $values): object
    {
        return new class ($values) {
            private array $values;

            public function __construct(array $values)
            {
                $this->values = $values;
            }

            public function query(string $key)
            {
                return $this->values[$key] ?? null;
            }
        };
    }

    public function test_text_comes_back_as_it_was_sent(): void
    {
        $this->assertSame('144534752', Query::text($this->request(['corporation' => '144534752']), 'corporation'));
    }

    public function test_a_list_reads_as_if_nothing_was_sent(): void
    {
        $this->assertSame('', Query::text($this->request(['corporation' => ['144534752']]), 'corporation'));
        $this->assertSame('', Query::text($this->request(['corporation' => ['x' => ['y' => '1']]]), 'corporation'));
    }

    public function test_nothing_sent_reads_as_empty(): void
    {
        $this->assertSame('', Query::text($this->request([]), 'corporation'));
    }
}
