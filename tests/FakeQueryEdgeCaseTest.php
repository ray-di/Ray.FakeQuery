<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;
use Ray\FakeQuery\Entity\NoteEntity;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Exception\InvalidFakeJsonException;
use Ray\FakeQuery\Exception\MissingFakeFieldException;
use Ray\FakeQuery\Exception\UnsupportedQueryException;
use Ray\FakeQuery\Query\EdgeQueryInterface;

final class FakeQueryEdgeCaseTest extends TestCase
{
    private EdgeQueryInterface $query;

    protected function setUp(): void
    {
        $injector = new Injector(new FakeQueryModule(__DIR__ . '/FakeEdge', [EdgeQueryInterface::class]), __DIR__ . '/tmp');
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

    public function testNonObjectJsonThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('scalar_item.json: expected a JSON object or null');

        $this->query->scalarItem();
    }

    public function testJsonArrayRowThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('array_item.json: expected a JSON object or null');

        $this->query->arrayItem();
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

    public function testNonObjectJsonlLineThrows(): void
    {
        $this->expectException(InvalidFakeJsonException::class);
        $this->expectExceptionMessage('scalar_line_list.jsonl line 1: expected a JSON object');

        $this->query->scalarLineList();
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
        $this->expectExceptionMessage('"userName" or "user_name"');

        $this->query->missingFieldUser();
    }

    public function testMissingNullableFieldIsNull(): void
    {
        $note = $this->query->missingFieldNote();

        $this->assertInstanceOf(NoteEntity::class, $note);
        $this->assertSame('n001', $note->noteId);
        $this->assertNull($note->noteBody);
    }

    public function testPostQueryRowsAreHydratedWithDocblockEntity(): void
    {
        $selection = $this->query->entitySelection();

        $this->assertCount(2, $selection->rows);
        $this->assertContainsOnlyInstancesOf(TodoEntity::class, $selection->rows);
        $this->assertSame($selection->pdo, $this->query->rawSelection()->pdo);
    }

    public function testPostQueryRowsWithoutEntityAreRaw(): void
    {
        $this->assertSame(
            ['todo_id' => '01HVEDGE1', 'todo_title' => 'First'],
            $this->query->rawSelection()->rows[0],
        );
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
        $this->expectExceptionMessage('DML metadata results');

        $this->query->update();
    }

    public function testInsertedRowIsUnsupported(): void
    {
        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage('DML metadata results');

        $this->query->insert();
    }
}
