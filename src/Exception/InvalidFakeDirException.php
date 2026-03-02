<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class InvalidFakeDirException extends LogicException
{
    public function __construct(string $fakeDir)
    {
        parent::__construct("Invalid fake directory: {$fakeDir}");
    }
}
