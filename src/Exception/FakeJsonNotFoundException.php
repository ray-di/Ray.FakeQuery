<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

use RuntimeException;

final class FakeJsonNotFoundException extends RuntimeException
{
    public function __construct(string $queryId, string $fakeDir)
    {
        parent::__construct("Fake JSON file not found: {$queryId}.json in {$fakeDir}");
    }
}
