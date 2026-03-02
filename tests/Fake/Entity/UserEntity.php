<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Entity;

final class UserEntity
{
    public function __construct(
        public readonly string $userId,
        public readonly string $userName,
        public readonly bool $isActive = true,
    ) {
    }
}
