<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\FakeQuery\Factory\StaticTodoFactory;
use Ray\FakeQuery\Result\TodoSelection;
use Ray\MediaQuery\Annotation\DbQuery;

interface TodoSelectionQueryInterface
{
    #[DbQuery('factory_static_list', factory: StaticTodoFactory::class)]
    public function list(): TodoSelection;
}
