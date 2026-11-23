<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

use Throwable;

final class InvalidFakeJsonException extends RuntimeException
{
    public function __construct(string $file, string $reason, Throwable|null $previous = null)
    {
        parent::__construct("Invalid fake JSON in {$file}: {$reason}", 0, $previous);
    }
}
