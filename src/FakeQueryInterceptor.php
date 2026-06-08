<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Override;
use PDO;
use PDOStatement;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\ParamConverterInterface;
use Ray\MediaQuery\ParamInjectorInterface;
use Ray\MediaQuery\Result\PostQueryContext;
use Ray\MediaQuery\Result\PostQueryInterface;
use Ray\MediaQuery\ReturnEntityInterface;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_slice;
use function array_values;
use function assert;
use function class_exists;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_subclass_of;
use function json_decode;
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
        private readonly ParamInjectorInterface $paramInjector,
        private readonly ParamConverterInterface $paramConverter,
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

        $values = $this->values($invocation);

        /** @var class-string|null $entityClass */
        $entityClass = ($this->returnEntity)($method);

        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();
            if (class_exists($typeName) && is_subclass_of($typeName, PostQueryInterface::class)) {
                return $this->selectPostQuery($typeName, $dbQuery, $entityClass, $values);
            }
        }

        $isRow = $dbQuery->type === 'row'
            || $returnType instanceof ReflectionUnionType
            || ($returnType instanceof ReflectionNamedType && $returnType->getName() !== 'array');

        if ($isRow) {
            $data = $this->readRow($dbQuery, $values);
            if ($data === null && $this->isNullable($returnType)) {
                return null;
            }

            if ($data === null) {
                throw new FakeJsonNotFoundException($dbQuery->id . '.json', $this->config->fakeDir);
            }

            return $this->hydrator->hydrate($data, $entityClass, true, $dbQuery);
        }

        $data = $this->readRows($dbQuery, $values);

        return $this->hydrator->hydrate($data, $entityClass, false, $dbQuery);
    }

    /**
     * @param MethodInvocation<object> $invocation
     *
     * @return array<string, mixed>
     */
    private function values(MethodInvocation $invocation): array
    {
        $values = $this->paramInjector->getArguments($invocation);
        ($this->paramConverter)($values);

        /** @var array<string, mixed> $values */
        return $values;
    }

    /**
     * @param class-string<PostQueryInterface> $postQueryClass
     * @param class-string|null                $entityClass
     * @param array<string, mixed>             $values
     */
    private function selectPostQuery(string $postQueryClass, DbQuery $dbQuery, string|null $entityClass, array $values): PostQueryInterface
    {
        $rows = $this->hydrator->hydrate(
            $this->readRows($dbQuery, $values),
            $dbQuery->factory === '' ? null : $entityClass,
            false,
            $dbQuery,
        );
        assert(is_array($rows));

        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->query('SELECT 1');
        assert($statement instanceof PDOStatement);

        return $postQueryClass::fromContext(new PostQueryContext($statement, new FakeQueryPdo($pdo), $values, $rows));
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return JsonRow|null
     */
    private function readRow(DbQuery $dbQuery, array $values): array|null
    {
        if ($this->hasFile($dbQuery, '.json')) {
            /** @var JsonRow $data */
            $data = json_decode($this->readContent($dbQuery, '.json'), true, 512, JSON_THROW_ON_ERROR);

            return $this->stripParams($data);
        }

        if (! $this->hasFile($dbQuery, '.jsonl')) {
            return null;
        }

        $rows = $this->selectRows($this->parseJsonl($this->readContent($dbQuery, '.jsonl')), $values);
        $row = $rows[0] ?? null;

        return $row === null ? null : $this->stripParams($row);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return JsonRowList
     */
    private function readRows(DbQuery $dbQuery, array $values): array
    {
        $rows = $this->parseJsonl($this->readContent($dbQuery, '.jsonl'));

        $selected = array_map(
            fn (array $row): array => $this->stripParams($row),
            $this->selectRows($rows, $values),
        );

        return $this->applyWindow($selected, $values);
    }

    private function readContent(DbQuery $dbQuery, string $ext): string
    {
        $jsonFile = $this->findFile($dbQuery, $ext);
        if ($jsonFile === null) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir);
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir); // @codeCoverageIgnore — file_exists passed
        }

        return $content;
    }

    private function findFile(DbQuery $dbQuery, string $ext): string|null
    {
        foreach ($this->config->fakeDirs as $fakeDir) {
            $jsonFile = $fakeDir . '/' . $dbQuery->id . $ext;
            if (file_exists($jsonFile)) {
                return $jsonFile;
            }
        }

        return null;
    }

    private function hasFile(DbQuery $dbQuery, string $ext): bool
    {
        return $this->findFile($dbQuery, $ext) !== null;
    }

    /**
     * @param JsonRowList         $rows
     * @param array<string,mixed> $values
     *
     * @return JsonRowList
     */
    private function selectRows(array $rows, array $values): array
    {
        $hasParams = false;
        foreach ($rows as $row) {
            if (isset($row['_params']) && is_array($row['_params'])) {
                $hasParams = true;
                break;
            }
        }

        if (! $hasParams) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->rowMatchesParams($row, $values),
        ));
    }

    /**
     * @param JsonRow             $row
     * @param array<string,mixed> $values
     */
    private function rowMatchesParams(array $row, array $values): bool
    {
        $params = $row['_params'] ?? null;
        if (! is_array($params) || ! $this->hasOnlyScalarParams($params)) {
            return false;
        }

        /** @var array<array-key, scalar|null> $params */
        return $this->matchesParams($params, $values);
    }

    /** @param array<array-key, mixed> $params */
    private function hasOnlyScalarParams(array $params): bool
    {
        return array_filter(
            $params,
            static fn (mixed $value): bool => ! is_scalar($value) && $value !== null,
        ) === [];
    }

    /**
     * @param array<array-key,scalar|null> $params
     * @param array<string,mixed>          $values
     */
    private function matchesParams(array $params, array $values): bool
    {
        foreach ($params as $key => $expected) {
            if (! array_key_exists($key, $values)) {
                return false;
            }

            if ($this->normalize($values[$key]) !== $this->normalize($expected)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return $value;
    }

    /**
     * @param JsonRowList         $rows
     * @param array<string,mixed> $values
     *
     * @return JsonRowList
     */
    private function applyWindow(array $rows, array $values): array
    {
        $offset = isset($values['offset']) && is_scalar($values['offset']) ? (int) $values['offset'] : 0;
        if ($offset < 0) {
            $offset = 0;
        }

        $limit = isset($values['limit']) && is_scalar($values['limit']) ? (int) $values['limit'] : null;
        if ($limit === null) {
            return $offset === 0 ? $rows : array_slice($rows, $offset);
        }

        if ($limit < 0) {
            $limit = 0;
        }

        return array_slice($rows, $offset, $limit);
    }

    /**
     * @param JsonRow $row
     *
     * @return JsonRow
     */
    private function stripParams(array $row): array
    {
        unset($row['_params']);

        return $row;
    }

    /** @return JsonRowList */
    private function parseJsonl(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $lines = array_values(array_filter(
            explode("\n", $trimmed),
            static fn (string $line): bool => $line !== '',
        ));

        /** @var JsonRowList $result */
        $result = array_map(
            static fn (string $line): mixed => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            $lines,
        );

        return $result;
    }

    private function isNullable(ReflectionType|null $returnType): bool
    {
        if ($returnType instanceof ReflectionNamedType) {
            return $returnType->allowsNull();
        }

        if (! ($returnType instanceof ReflectionUnionType)) { // @codeCoverageIgnore
            return false; // @codeCoverageIgnore
        }

        foreach ($returnType->getTypes() as $type) { // @codeCoverageIgnore
            if ($type instanceof ReflectionNamedType && $type->getName() === 'null') { // @codeCoverageIgnore
                return true; // @codeCoverageIgnore
            }
        }

        return false; // @codeCoverageIgnore
    }
}
