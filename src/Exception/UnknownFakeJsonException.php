<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class UnknownFakeJsonException extends RuntimeException
{
    public function __construct(string $filename, string $fakeDir)
    {
        parent::__construct("Unknown fake JSON file: {$filename} in {$fakeDir} has no matching #[DbQuery] id");
    }
}
