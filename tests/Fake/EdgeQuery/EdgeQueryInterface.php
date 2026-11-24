<?php

declare(strict_types=1);

namespace Ray\FakeQuery\EdgeQuery;

use Ray\FakeQuery\Entity\FactoryTodoEntity;
use Ray\FakeQuery\Entity\NoteEntity;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Entity\UserEntity;
use Ray\FakeQuery\Factory\InjectedTodoFactory;
use Ray\FakeQuery\Factory\MissingMethodTodoFactory;
use Ray\FakeQuery\Factory\NonPublicMethodTodoFactory;
use Ray\FakeQuery\Factory\StaticTodoFactory;
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

    #[DbQuery('array_item')]
    public function arrayItem(): TodoEntity|null;

    #[DbQuery('scalar_item')]
    public function scalarItem(): TodoEntity|null;

    /** @return array<TodoEntity> */
    #[DbQuery('blank_line_list')]
    public function blankLineList(): array;

    /** @return list<array<string, mixed>> */
    #[DbQuery('blank_line_list')]
    public function rawList(): array;

    /** @return array<TodoEntity> */
    #[DbQuery('broken_line_list')]
    public function brokenLineList(): array;

    /** @return list<array<string, mixed>> */
    #[DbQuery('array_line_list')]
    public function arrayLineList(): array;

    #[DbQuery('missing_field_user')]
    public function missingFieldUser(): UserEntity;

    #[DbQuery('missing_field_note')]
    public function missingFieldNote(): NoteEntity;

    #[DbQuery('blank_line_list')]
    #[Pager(perPage: 2)]
    public function pager(): PagesInterface;

    #[DbQuery('todo_update')]
    public function update(): AffectedRows;

    #[DbQuery('todo_insert')]
    public function insert(): InsertedRow;

    #[DbQuery('factory_item', factory: StaticTodoFactory::class)]
    public function staticFactoryItem(): FactoryTodoEntity;

    #[DbQuery('factory_item', factory: InjectedTodoFactory::class)]
    public function injectedFactoryItem(): FactoryTodoEntity;

    /** @return array<FactoryTodoEntity> */
    #[DbQuery('factory_list', factory: StaticTodoFactory::class)]
    public function staticFactoryList(): array;

    #[DbQuery('factory_missing_field_item', factory: StaticTodoFactory::class)]
    public function factoryMissingField(): FactoryTodoEntity;

    #[DbQuery('factory_item', factory: 'Ray\FakeQuery\Factory\UndefinedTodoFactory')]
    public function missingFactoryClass(): FactoryTodoEntity;

    #[DbQuery('factory_item', factory: MissingMethodTodoFactory::class)]
    public function missingFactoryMethod(): FactoryTodoEntity;

    #[DbQuery('factory_item', factory: NonPublicMethodTodoFactory::class)]
    public function nonPublicFactoryMethod(): FactoryTodoEntity;
}
