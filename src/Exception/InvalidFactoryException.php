<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class InvalidFactoryException extends LogicException
{
    public function __construct(string $factoryClass, string $factoryMethod)
    {
        parent::__construct("Invalid fake query factory: {$factoryClass}::{$factoryMethod}()");
    }
}
