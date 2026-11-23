<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Result;

use Aura\Sql\ExtendedPdoInterface;
use Override;
use Ray\MediaQuery\Result\PostQueryContext;
use Ray\MediaQuery\Result\PostQueryInterface;

/** @template T */
final readonly class RowSelection implements PostQueryInterface
{
    /** @param array<T> $rows */
    private function __construct(
        public array $rows,
        public ExtendedPdoInterface $pdo,
    ) {
    }

    #[Override]
    public static function fromContext(PostQueryContext $context): static
    {
        /** @var array<T> $rows */
        $rows = $context->rows;

        return new self($rows, $context->pdo);
    }
}
