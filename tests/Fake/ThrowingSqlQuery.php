<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\MediaQuery\FetchInterface;
use Ray\MediaQuery\PagesInterface;
use Ray\MediaQuery\Result\PostQueryInterface;
use Ray\MediaQuery\SqlQueryInterface;
use RuntimeException;

final class ThrowingSqlQuery implements SqlQueryInterface
{
    /** @param array<string, mixed> $values */
    public function getRow(string $sqlId, array $values = [], FetchInterface|null $fetch = null): array|object|null
    {
        throw new RuntimeException('Real SQL getRow should not be called.');
    }

    /** @param array<string, mixed> $values */
    public function getRowList(string $sqlId, array $values = [], FetchInterface|null $fetch = null): array
    {
        throw new RuntimeException('Real SQL getRowList should not be called.');
    }

    /** @param array<string, mixed> $values */
    public function exec(string $sqlId, array $values = [], FetchInterface|null $fetch = null): void
    {
        throw new RuntimeException('Real SQL exec should not be called.');
    }

    /** @param array<string, mixed> $values */
    public function execPostQuery(
        string $sqlId,
        array $values,
        string $postQueryClass,
        FetchInterface|null $fetch = null,
    ): PostQueryInterface {
        throw new RuntimeException('Real SQL execPostQuery should not be called.');
    }

    /** @param array<string, mixed> $values */
    public function getCount(string $sqlId, array $values): int
    {
        throw new RuntimeException('Real SQL getCount should not be called.');
    }

    /** @param array<string, mixed> $values */
    public function getPages(
        string $sqlId,
        array $values,
        int $perPage,
        string $queryTemplate = '/{?page}',
        string|null $entity = null,
    ): PagesInterface {
        throw new RuntimeException('Real SQL getPages should not be called.');
    }
}
