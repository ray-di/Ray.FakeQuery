<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class FakeJsonNotFoundException extends RuntimeException
{
    public function __construct(string $filename, string $fakeDir)
    {
        parent::__construct("Fake JSON file not found: {$filename} in {$fakeDir}");
    }
}
