<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use JsonException;
use Override;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Aop\ReflectionMethod;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\FakeQuery\Exception\InvalidFakeJsonException;
use Ray\FakeQuery\Exception\UnsupportedQueryException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Pager;
use Ray\MediaQuery\PagesInterface;
use Ray\MediaQuery\Result\PostQueryInterface;
use Ray\MediaQuery\ReturnEntityInterface;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function assert;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_a;
use function is_array;
use function json_decode;
use function ltrim;
use function str_starts_with;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * @psalm-import-type JsonRow from Types
 * @psalm-import-type JsonRowList from Types
 */
final class FakeQueryInterceptor implements MethodInterceptor
{
    public function __construct(
        private readonly FakeQueryConfig $config,
        private readonly JsonHydrator $hydrator,
        private readonly ReturnEntityInterface $returnEntity,
    ) {
    }

    #[Override]
    public function invoke(MethodInvocation $invocation): mixed
    {
        $method = $invocation->getMethod();
        $dbQuery = $method->getAnnotation(DbQuery::class);
        assert($dbQuery instanceof DbQuery);

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return null;
        }

        $this->assertSupported($method, $dbQuery, $returnType);

        $isRow = $dbQuery->type === 'row'
            || $returnType instanceof ReflectionUnionType
            || ($returnType instanceof ReflectionNamedType && $returnType->getName() !== 'array');

        $ext = $isRow ? '.json' : '.jsonl';
        $jsonFile = $this->config->fakeDir . '/' . $dbQuery->id . $ext;

        if (! file_exists($jsonFile)) {
            if ($this->isNullable($returnType)) {
                return null;
            }

            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir);
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir); // @codeCoverageIgnore
        }

        $data = $isRow
            ? $this->decodeRow($content, $jsonFile, $this->isNullable($returnType))
            : $this->parseJsonl($content, $jsonFile);

        $entityClass = ($this->returnEntity)($method);

        return $this->hydrator->hydrate($data, $entityClass, $isRow, $dbQuery->factory);
    }

    private function assertSupported(ReflectionMethod $method, DbQuery $dbQuery, ReflectionType|null $returnType): void
    {
        if ($method->getAnnotation(Pager::class) instanceof Pager || $this->returns($returnType, PagesInterface::class)) {
            throw new UnsupportedQueryException($dbQuery->id, $method->name, '#[Pager] / PagesInterface results');
        }

        // Covers AffectedRows and InsertedRow, which need a statement that a fixture cannot provide.
        if ($this->returns($returnType, PostQueryInterface::class)) {
            throw new UnsupportedQueryException($dbQuery->id, $method->name, 'PostQueryInterface results');
        }
    }

    /** @param class-string $class */
    private function returns(ReflectionType|null $returnType, string $class): bool
    {
        $types = $returnType instanceof ReflectionUnionType ? $returnType->getTypes() : [$returnType];
        foreach ($types as $type) {
            if ($type instanceof ReflectionNamedType && is_a($type->getName(), $class, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return ($nullable is true ? JsonRow|null : JsonRow) */
    private function decodeRow(string $json, string $location, bool $nullable): array|null
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidFakeJsonException($location, $e->getMessage(), $e);
        }

        if ($data === null && $nullable) {
            return null;
        }

        // Associative decoding turns JSON arrays into PHP arrays too; only an object starts with "{".
        if (! is_array($data) || ! str_starts_with(ltrim($json), '{')) {
            throw new InvalidFakeJsonException($location, $nullable ? 'expected a JSON object or null' : 'expected a JSON object');
        }

        /** @var JsonRow $data */
        return $data;
    }

    /** @return JsonRowList */
    private function parseJsonl(string $content, string $file): array
    {
        $rows = [];
        foreach (explode("\n", $content) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = $this->decodeRow($line, $file . ' line ' . ($index + 1), false);
        }

        return $rows;
    }

    private function isNullable(ReflectionType|null $returnType): bool
    {
        return $returnType !== null && $returnType->allowsNull();
    }
}
