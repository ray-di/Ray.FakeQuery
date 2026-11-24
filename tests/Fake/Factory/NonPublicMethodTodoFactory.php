<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Factory;

use LogicException;

class NonPublicMethodTodoFactory
{
    protected static function factory(): never
    {
        throw new LogicException('A non-public factory method must not be called');
    }
}
