<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Entity;

class TodoEntity
{
    public string $todoId = '';
    public string $todoTitle = '';
    public bool $isCompleted = false;
}
