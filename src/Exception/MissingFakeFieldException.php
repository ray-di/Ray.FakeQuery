<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class MissingFakeFieldException extends RuntimeException
{
    public function __construct(string $entityClass, string $parameter, string $field)
    {
        parent::__construct("Fake row has no \"{$parameter}\" or \"{$field}\" field for the required constructor parameter \${$parameter} of {$entityClass}");
    }
}
