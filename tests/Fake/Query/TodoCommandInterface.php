<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\MediaQuery\Annotation\DbQuery;

interface TodoCommandInterface
{
    #[DbQuery('todo_add')]
    public function add(string $todoId, string $title): void;
}
