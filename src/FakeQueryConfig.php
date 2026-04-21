<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\FakeQuery\Exception\InvalidFakeDirException;

use function is_dir;
use function is_readable;

final class FakeQueryConfig
{
    public function __construct(
        public readonly string $fakeDir,
    ) {
        if (is_dir($fakeDir) && ! is_readable($fakeDir)) {
            throw new InvalidFakeDirException($fakeDir);
        }
    }
}
