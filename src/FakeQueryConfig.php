<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\FakeQuery\Exception\InvalidFakeDirException;

use function is_dir;

final class FakeQueryConfig
{
    public function __construct(
        public readonly string $fakeDir,
    ) {
        if (! is_dir($fakeDir)) {
            throw new InvalidFakeDirException($fakeDir);
        }
    }
}
