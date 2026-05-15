<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\FakeQuery\Exception\InvalidFakeDirException;

use function is_dir;
use function is_readable;
use function rtrim;

use const DIRECTORY_SEPARATOR;

final class FakeQueryConfig
{
    public readonly string $fakeDir;

    public function __construct(string $fakeDir)
    {
        $this->fakeDir = rtrim($fakeDir, DIRECTORY_SEPARATOR);
        if (! is_dir($this->fakeDir) || ! is_readable($this->fakeDir)) {
            throw new InvalidFakeDirException($fakeDir);
        }
    }
}
