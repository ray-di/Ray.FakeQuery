<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use PHPUnit\Framework\TestCase;

final class FakeQueryTest extends TestCase
{
    protected FakeQuery $fakeQuery;

    protected function setUp(): void
    {
        $this->fakeQuery = new FakeQuery();
    }

    public function testIsInstanceOfFakeQuery(): void
    {
        $actual = $this->fakeQuery;
        $this->assertInstanceOf(FakeQuery::class, $actual);
    }
}
