<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\FakeQuery\Entity\FactoryTodoEntity;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Entity\UserEntity;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\FakeQuery\Exception\InvalidFactoryException;
use Ray\FakeQuery\Exception\InvalidFakeDirException;
use Ray\FakeQuery\Exception\UnknownFakeJsonException;
use Ray\FakeQuery\Query\FactoryTodoQueryInterface;
use Ray\FakeQuery\Query\TodoCommandInterface;
use Ray\FakeQuery\Query\TodoQueryInterface;
use Ray\FakeQuery\Query\TodoSelectionQueryInterface;
use Ray\FakeQuery\Query\UserQueryInterface;
use Ray\FakeQuery\Result\TodoSelection;
use Ray\MediaQuery\DbQueryConfig;
use Ray\MediaQuery\MediaQueryModule;
use Ray\MediaQuery\Queries;
use Ray\MediaQuery\SqlQueryInterface;

final class FakeQueryModuleTest extends TestCase
{
    private TodoQueryInterface $query;
    private TodoCommandInterface $command;
    private UserQueryInterface $userQuery;
    private FactoryTodoQueryInterface $factoryQuery;
    private TodoSelectionQueryInterface $selectionQuery;

    protected function setUp(): void
    {
        $fakeDir = __DIR__ . '/Fake';
        $interfaceDir = __DIR__ . '/Fake/Query';

        $injector = new Injector(new class ($fakeDir, $interfaceDir) extends AbstractModule {
            public function __construct(
                private readonly string $fakeDir,
                private readonly string $interfaceDir,
            ) {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->install(new FakeQueryModule($this->fakeDir, $this->interfaceDir));
            }
        }, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);
        $this->query = $query;

        /** @var TodoCommandInterface $command */
        $command = $injector->getInstance(TodoCommandInterface::class);
        $this->command = $command;

        /** @var UserQueryInterface $userQuery */
        $userQuery = $injector->getInstance(UserQueryInterface::class);
        $this->userQuery = $userQuery;

        /** @var FactoryTodoQueryInterface $factoryQuery */
        $factoryQuery = $injector->getInstance(FactoryTodoQueryInterface::class);
        $this->factoryQuery = $factoryQuery;

        /** @var TodoSelectionQueryInterface $selectionQuery */
        $selectionQuery = $injector->getInstance(TodoSelectionQueryInterface::class);
        $this->selectionQuery = $selectionQuery;
    }

    public function testItemReturnsEntity(): void
    {
        $todo = $this->query->item('01HVXXXXXX0008');

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame('01HVXXXXXX0008', $todo->todoId);
        $this->assertSame('Write Be Framework tutorial', $todo->todoTitle);
        $this->assertFalse($todo->isCompleted);
    }

    public function testListReturnsEntityArrayFromJsonl(): void
    {
        $list = $this->query->list();

        $this->assertCount(2, $list);
        $this->assertContainsOnlyInstancesOf(TodoEntity::class, $list);
        $this->assertSame('01HVXXXXXX0008', $list[0]->todoId);
        $this->assertTrue($list[1]->isCompleted);
    }

    public function testExplicitRowTypeReturnsRawArray(): void
    {
        $result = $this->query->itemExplicitRow('01HVXXXXXX0008');

        $this->assertSame('01HVXXXXXX0008', $result['todoId']);
    }

    public function testCommandIsNoOp(): void
    {
        $this->command->add('01HVXXXXXX0008', 'test');
        $this->addToAssertionCount(1);
    }

    public function testConstructorHydrationWithCamelCaseAndDefault(): void
    {
        $user = $this->userQuery->item('u001');

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertSame('u001', $user->userId);
        $this->assertSame('Alice', $user->userName);
        $this->assertTrue($user->isActive);
    }

    public function testConstructorHydrationList(): void
    {
        $list = $this->userQuery->list();

        $this->assertCount(2, $list);
        $this->assertContainsOnlyInstancesOf(UserEntity::class, $list);
        $this->assertSame('Bob', $list[1]->userName);
        $this->assertFalse($list[1]->isActive);
    }

    public function testStaticFactoryHydration(): void
    {
        $todo = $this->factoryQuery->staticItem();

        $this->assertInstanceOf(FactoryTodoEntity::class, $todo);
        $this->assertSame('01HVFACTORY1', $todo->todoId);
        $this->assertSame('Static factory item', $todo->todoTitle);
        $this->assertSame('2026-01-01 00:00:00', $todo->createdAt->format('Y-m-d H:i:s'));
    }

    public function testInjectedFactoryHydration(): void
    {
        $todo = $this->factoryQuery->injectedItem();

        $this->assertInstanceOf(FactoryTodoEntity::class, $todo);
        $this->assertSame('01HVFACTORY2', $todo->todoId);
        $this->assertSame('Injected factory item', $todo->todoTitle);
        $this->assertSame('2026-01-02 00:00:00', $todo->createdAt->format('Y-m-d H:i:s'));
    }

    public function testStaticFactoryListHydration(): void
    {
        $list = $this->factoryQuery->staticList();

        $this->assertContainsOnlyInstancesOf(FactoryTodoEntity::class, $list);
        $this->assertSame('01HVFACTORY3', $list[0]->todoId);
        $this->assertSame('2026-01-04 00:00:00', $list[1]->createdAt->format('Y-m-d H:i:s'));
    }

    public function testNestedQueryIdFixture(): void
    {
        $todo = $this->factoryQuery->nestedStaticItem();

        $this->assertSame('01HVNESTED1', $todo->todoId);
        $this->assertSame('Nested static factory item', $todo->todoTitle);
    }

    public function testMissingFactoryClassThrows(): void
    {
        $this->expectException(InvalidFactoryException::class);
        $this->expectExceptionMessage('Ray\FakeQuery\Factory\MissingTodoFactory::factory()');

        $this->factoryQuery->missingFactoryClass();
    }

    public function testMissingFactoryMethodThrows(): void
    {
        $this->expectException(InvalidFactoryException::class);
        $this->expectExceptionMessage('Ray\FakeQuery\Factory\MissingMethodTodoFactory::factory()');

        $this->factoryQuery->missingFactoryMethod();
    }

    public function testPostQuerySelectionHydratesFactoryRows(): void
    {
        $selection = $this->selectionQuery->list();

        $this->assertInstanceOf(TodoSelection::class, $selection);
        $this->assertCount(2, $selection);
        $this->assertSame(['Static factory list 1', 'Static factory list 2'], $selection->titles());
    }

    public function testFakeQueryOverridesExistingMediaQueryInterceptors(): void
    {
        $fakeDir = __DIR__ . '/Fake';
        $interfaceDir = __DIR__ . '/Fake/Query';
        $module = new class ($interfaceDir) extends AbstractModule {
            public function __construct(private readonly string $interfaceDir)
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                $this->install(new MediaQueryModule(
                    Queries::fromDir($this->interfaceDir),
                    [new DbQueryConfig(__DIR__ . '/FakeSql')],
                ));
                $this->bind(SqlQueryInterface::class)->to(ThrowingSqlQuery::class);
            }
        };
        $module->override(new FakeQueryModule($fakeDir, $interfaceDir));
        $injector = new Injector($module, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);
        $todo = $query->item('01HVXXXXXX0008');

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame('Write Be Framework tutorial', $todo->todoTitle);
    }

    public function testUnionNullableWithMissingFileReturnsNull(): void
    {
        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/FakeEmpty',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');

        /** @var UserQueryInterface $query */
        $query = $injector->getInstance(UserQueryInterface::class);

        $result = $query->item('1');

        $this->assertNull($result);
    }

    public function testNullableWithMissingFileReturnsNull(): void
    {
        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/FakeEmpty',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);

        $result = $query->item('1');

        $this->assertNull($result);
    }

    public function testNonNullableWithMissingFileThrows(): void
    {
        $this->expectException(FakeJsonNotFoundException::class);
        $this->expectExceptionMessage('todo_list.jsonl');

        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/FakeEmpty',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);
        $query->list();
    }

    public function testInvalidFakeDirThrows(): void
    {
        $this->expectException(InvalidFakeDirException::class);
        $this->expectExceptionMessage('/nonexistent/dir');

        new FakeQueryConfig('/nonexistent/dir');
    }

    public function testFakeDirTrailingSeparatorIsNormalized(): void
    {
        $config = new FakeQueryConfig(__DIR__ . '/Fake/');

        $this->assertSame(__DIR__ . '/Fake', $config->fakeDir);
    }

    public function testFakeQueryModuleAcceptsTrailingFakeDirSeparator(): void
    {
        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/Fake/',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);
        $todo = $query->item('01HVXXXXXX0008');

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame('Write Be Framework tutorial', $todo->todoTitle);
    }

    public function testUnknownFakeJsonFileThrows(): void
    {
        $this->expectException(UnknownFakeJsonException::class);
        $this->expectExceptionMessage('stray_query.json');

        new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/FakeUnknown',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');
    }

    public function testUnknownNestedFakeJsonFileThrows(): void
    {
        $this->expectException(UnknownFakeJsonException::class);
        $this->expectExceptionMessage('stray_query.json');

        new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    __DIR__ . '/FakeUnknownNested',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');
    }
}
