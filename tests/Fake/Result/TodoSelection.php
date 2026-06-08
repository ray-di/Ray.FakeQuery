<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Ray\FakeQuery\Entity\FactoryTodoEntity;
use Ray\MediaQuery\Result\PostQueryContext;
use Ray\MediaQuery\Result\PostQueryInterface;

use function array_map;
use function count;

/**
 * @implements IteratorAggregate<int, FactoryTodoEntity>
 */
final readonly class TodoSelection implements PostQueryInterface, Countable, IteratorAggregate
{
    /**
     * @param list<FactoryTodoEntity> $rows
     * @param array<string, mixed>    $values
     */
    private function __construct(
        public array $rows,
        public array $values,
    ) {
    }

    public static function fromContext(PostQueryContext $context): static
    {
        /** @var list<FactoryTodoEntity> $rows */
        $rows = $context->rows;

        return new self($rows, $context->values);
    }

    /** @return list<string> */
    public function titles(): array
    {
        return array_map(static fn (FactoryTodoEntity $todo): string => $todo->todoTitle, $this->rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /** @return ArrayIterator<int, FactoryTodoEntity> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->rows);
    }
}
