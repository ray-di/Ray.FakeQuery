<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Factory;

class NonPublicMethodTodoFactory
{
    protected function factory(): never
    {
        throw new \LogicException('A non-public factory method must not be called');
    }
}
