<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\FakeQuery\Entity\NoteEntity;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Factory\NonPublicMethodTodoFactory;
use Ray\FakeQuery\Entity\UserEntity;
use Ray\FakeQuery\Result\RowSelection;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Pager;
use Ray\MediaQuery\PagesInterface;
use Ray\MediaQuery\Result\AffectedRows;
use Ray\MediaQuery\Result\InsertedRow;

interface EdgeQueryInterface
{
    #[DbQuery('null_item')]
    public function nullItem(): TodoEntity|null;

    #[DbQuery('broken_item')]
    public function brokenItem(): TodoEntity|null;

    #[DbQuery('scalar_item')]
    public function scalarItem(): TodoEntity|null;

    #[DbQuery('array_item')]
    public function arrayItem(): TodoEntity|null;

    /** @return array<TodoEntity> */
    #[DbQuery('blank_line_list')]
    public function blankLineList(): array;

    /** @return list<array<string, mixed>> */
    #[DbQuery('blank_line_list')]
    public function rawList(): array;

    /** @return array<TodoEntity> */
    #[DbQuery('broken_line_list')]
    public function brokenLineList(): array;

    /** @return array<TodoEntity> */
    #[DbQuery('scalar_line_list')]
    public function scalarLineList(): array;

    /** @return list<array<string, mixed>> */
    #[DbQuery('array_line_list')]
    public function arrayLineList(): array;

    #[DbQuery('missing_field_user')]
    public function missingFieldUser(): UserEntity;

    #[DbQuery('missing_field_note')]
    public function missingFieldNote(): NoteEntity;

    /** @return RowSelection<TodoEntity> */
    #[DbQuery('blank_line_list')]
    public function entitySelection(): RowSelection;

    /** @return RowSelection<array<string, mixed>> */
    #[DbQuery('blank_line_list')]
    public function rawSelection(): RowSelection;

    #[DbQuery('blank_line_list')]
    #[Pager(perPage: 2)]
    public function pager(): PagesInterface;

    #[DbQuery('todo_update')]
    public function update(): AffectedRows;

    #[DbQuery('todo_insert')]
    public function insert(): InsertedRow;

    #[DbQuery('missing_item')]
    public function missingItem(): TodoEntity;

    /** @return array<TodoEntity> */
    #[DbQuery('filtered_list')]
    public function filteredList(bool $isCompleted, int $limit = 10, int $offset = 0): array;

    #[DbQuery('private_factory_item', factory: NonPublicMethodTodoFactory::class)]
    public function privateFactoryItem(): TodoEntity;
}
