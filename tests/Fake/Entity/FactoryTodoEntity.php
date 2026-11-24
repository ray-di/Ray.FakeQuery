<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Entity;

use DateTimeImmutable;

final class FactoryTodoEntity
{
    public function __construct(
        public readonly string $todoId,
        public readonly string $todoTitle,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
