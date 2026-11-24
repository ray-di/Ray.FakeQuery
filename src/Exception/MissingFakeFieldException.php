<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class MissingFakeFieldException extends RuntimeException
{
    /** @param string $field How the parameter is looked up in the row, e.g. `"userName" or "user_name" field` */
    public function __construct(string $callee, string $parameter, string $field)
    {
        parent::__construct("Fake row has no {$field} for the required parameter \${$parameter} of {$callee}");
    }
}
