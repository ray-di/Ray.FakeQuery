<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\FakeQuery\Query\TodoCommandInterface;
use Ray\FakeQuery\Query\TodoQueryInterface;

final class FakeQueryModuleTest extends TestCase
{
    private TodoQueryInterface $query;
    private TodoCommandInterface $command;

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
    }

    public function testItemReturnsEntity(): void
    {
        $todo = $this->query->item('01HVXXXXXX0008');

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame('01HVXXXXXX0008', $todo->todoId);
        $this->assertSame('Beフレームワークのチュートリアルを書く', $todo->todoTitle);
        $this->assertFalse($todo->isCompleted);
    }

    public function testListReturnsEntityArray(): void
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

    public function testMissingJsonThrowsException(): void
    {
        $this->expectException(FakeJsonNotFoundException::class);
        $this->expectExceptionMessage('todo_item.json');

        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    '/nonexistent/dir',
                    __DIR__ . '/Fake/Query',
                ));
            }
        }, __DIR__ . '/tmp');

        /** @var TodoQueryInterface $query */
        $query = $injector->getInstance(TodoQueryInterface::class);
        $query->item('1');
    }
}
