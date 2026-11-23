<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\FakeQuery\Entity\FactoryTodoEntity;
use Ray\FakeQuery\Factory\InjectedTodoFactory;
use Ray\FakeQuery\Factory\MissingMethodTodoFactory;
use Ray\FakeQuery\Factory\StaticTodoFactory;
use Ray\MediaQuery\Annotation\DbQuery;

interface FactoryTodoQueryInterface
{
    #[DbQuery('factory_static_item', factory: StaticTodoFactory::class)]
    public function staticItem(): FactoryTodoEntity;

    #[DbQuery('factory_injected_item', factory: InjectedTodoFactory::class)]
    public function injectedItem(): FactoryTodoEntity;

    /** @return array<FactoryTodoEntity> */
    #[DbQuery('factory_static_list', factory: StaticTodoFactory::class)]
    public function staticList(): array;

    #[DbQuery('nested/factory_static_item', factory: StaticTodoFactory::class)]
    public function nestedStaticItem(): FactoryTodoEntity;

    #[DbQuery('factory_missing_class_item', factory: 'Ray\FakeQuery\Factory\MissingTodoFactory')]
    public function missingFactoryClass(): FactoryTodoEntity;

    #[DbQuery('factory_missing_method_item', factory: MissingMethodTodoFactory::class)]
    public function missingFactoryMethod(): FactoryTodoEntity;
}
