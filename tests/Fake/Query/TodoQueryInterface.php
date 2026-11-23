<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\FakeQuery\Entity\TodoEntity;
use Ray\MediaQuery\Annotation\DbQuery;

interface TodoQueryInterface
{
    #[DbQuery('todo_item')]
    public function item(string $todoId): ?TodoEntity;

    /** @return array<TodoEntity> */
    #[DbQuery('todo_list')]
    public function list(): array;

    /** @return array<TodoEntity> */
    #[DbQuery('todo_by_status')]
    public function listByStatus(bool $isCompleted, int $limit = 10, int $offset = 0): array;

    #[DbQuery('todo_by_status', type: 'row')]
    public function firstByStatus(bool $isCompleted): ?TodoEntity;

    /** @return array<string, mixed> */
    #[DbQuery('todo_item', type: 'row')]
    public function itemExplicitRow(string $todoId): array;
}
