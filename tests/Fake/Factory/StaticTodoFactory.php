<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Factory;

use DateTimeImmutable;
use Ray\FakeQuery\Entity\FactoryTodoEntity;

final class StaticTodoFactory
{
    public static function factory(string $todoId, string $todoTitle, string $createdAt): FactoryTodoEntity
    {
        return new FactoryTodoEntity($todoId, $todoTitle, new DateTimeImmutable($createdAt));
    }
}
