<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

final class FakeQueryConfig
{
    public function __construct(
        public readonly string $fakeDir,
    ) {
    }
}
