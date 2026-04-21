<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class InvalidFakeDirException extends \InvalidArgumentException
{
    public function __construct(string $fakeDir)
    {
        parent::__construct("Fake directory is not readable: {$fakeDir}");
    }
}
