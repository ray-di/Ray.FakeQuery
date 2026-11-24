<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\FakeQuery\EdgeQuery\EdgeQueryInterface;
use Ray\FakeQuery\Entity\FactoryTodoEntity;
use Ray\FakeQuery\Entity\NoteEntity;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Exception\InvalidFactoryException;
use Ray\FakeQuery\Exception\InvalidFakeJsonException;
use Ray\FakeQuery\Exception\MissingFakeFieldException;
use Ray\FakeQuery\Exception\UnsupportedQueryException;
use Ray\FakeQuery\Factory\InjectedTodoFactory;

final class FakeQueryEdgeCaseTest extends TestCase
{
    private EdgeQueryInterface $query;

    protected function setUp(): void
    {
        $factories = new class extends AbstractModule {
            protected function configure(): void
            {
                $this->bind(InjectedTodoFactory::class);
            }
        };
        $injector = new Injector(new FakeQueryModule(__DIR__ . '/FakeEdge', __DIR__ . '/Fake/EdgeQuery', $factories), __DIR__ . '/tmp');
        $query = $injector->getInstance(EdgeQueryInterface::class);
        $this->assertInstanceOf(EdgeQueryInterface::class, $query);
        $this->query = $query;
    }

    public function testNullJsonReturnsNull(): void
    {
        $this->assertNull($this->query->nullItem());
    }

    public function testBrokenJsonNamesTheFile(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('broken_item.json: Syntax error');

        $this->query->brokenItem();
    }

    public function testJsonArrayRowThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('array_item.json: expected a JSON object or null');

        $this->query->arrayItem();
    }

    public function testScalarJsonRowThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('scalar_item.json: expected a JSON object or null');

        $this->query->scalarItem();
    }

    public function testBlankJsonlLinesAreSkipped(): void
    {
        $list = $this->query->blankLineList();

        $this->assertCount(2, $list);
        $this->assertContainsOnlyInstancesOf(TodoEntity::class, $list);
        $this->assertSame('Second', $list[1]->todoTitle);
    }

    public function testListWithoutEntityReturnsRawRows(): void
    {
        $this->assertSame([
            ['todo_id' => '01HVEDGE1', 'todo_title' => 'First'],
            ['todo_id' => '01HVEDGE2', 'todo_title' => 'Second'],
        ], $this->query->rawList());
    }

    public function testBrokenJsonlLineNamesFileAndLine(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('broken_line_list.jsonl line 2: Syntax error');

        $this->query->brokenLineList();
    }

    public function testJsonlArrayLineThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('array_line_list.jsonl line 2: expected a JSON object');

        $this->query->arrayLineList();
    }

    public function testMissingRequiredFieldThrows(): void
    {
        $this->expectException(MissingFakeFieldException::class);
        $this->expectExceptionMessage('"userName" or "user_name" field for the required parameter $userName of Ray\FakeQuery\Entity\UserEntity::__construct()');

        $this->query->missingFieldUser();
    }

    public function testMissingNullableFieldIsNull(): void
    {
        $note = $this->query->missingFieldNote();

        $this->assertSame('n001', $note->noteId);
        $this->assertNull($note->noteBody);
        $this->assertInstanceOf(NoteEntity::class, $note);
    }

    public function testPagerIsUnsupported(): void
    {
        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage("#[Pager] / PagesInterface results: #[DbQuery('blank_line_list')] pager()");

        $this->query->pager();
    }

    public function testAffectedRowsIsUnsupported(): void
    {
        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage("PostQueryInterface results: #[DbQuery('todo_update')] update()");

        $this->query->update();
    }

    public function testInsertedRowIsUnsupported(): void
    {
        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage("PostQueryInterface results: #[DbQuery('todo_insert')] insert()");

        $this->query->insert();
    }

    public function testUnionOfResultTypesIsUnsupported(): void
    {
        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage("PostQueryInterface results: #[DbQuery('todo_write')] write()");

        $this->query->write();
    }

    public function testStaticFactoryReceivesFieldsInObjectOrder(): void
    {
        $todo = $this->query->staticFactoryItem();

        $this->assertSame('t001', $todo->todoId);
        $this->assertSame('Factory', $todo->todoTitle);
        $this->assertSame('2026-01-02', $todo->createdAt->format('Y-m-d'));
    }

    public function testInjectedFactoryIsResolvedByTheInjector(): void
    {
        $todo = $this->query->injectedFactoryItem();

        $this->assertSame('injected:t001', $todo->todoId);
        $this->assertSame('2026-01-02', $todo->createdAt->format('Y-m-d'));
    }

    public function testFactoryBuildsEachListRow(): void
    {
        $list = $this->query->staticFactoryList();

        $this->assertContainsOnlyInstancesOf(FactoryTodoEntity::class, $list);
        $this->assertSame('2026-01-03', $list[1]->createdAt->format('Y-m-d'));
    }

    public function testMissingFactoryFieldThrows(): void
    {
        $this->expectException(MissingFakeFieldException::class);
        $this->expectExceptionMessage('field at position 3 for the required parameter $createdAt of Ray\FakeQuery\Factory\StaticTodoFactory::factory()');

        $this->query->factoryMissingField();
    }

    public function testUndefinedFactoryClassThrows(): void
    {
        $this->expectException(InvalidFactoryException::class);
        $this->expectExceptionMessage('Ray\FakeQuery\Factory\UndefinedTodoFactory::factory()');

        $this->query->missingFactoryClass();
    }

    public function testUndefinedFactoryMethodThrows(): void
    {
        $this->expectException(InvalidFactoryException::class);

        $this->query->missingFactoryMethod();
    }

    public function testNonPublicFactoryMethodThrows(): void
    {
        $this->expectException(InvalidFactoryException::class);

        $this->query->nonPublicFactoryMethod();
    }
}
