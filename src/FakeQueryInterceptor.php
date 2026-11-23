<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Aura\Sql\ExtendedPdo;
use JsonException;
use Override;
use PDOStatement;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Aop\ReflectionMethod;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\FakeQuery\Exception\InvalidFakeJsonException;
use Ray\FakeQuery\Exception\UnsupportedQueryException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Pager;
use Ray\MediaQuery\PagesInterface;
use Ray\MediaQuery\ParamConverterInterface;
use Ray\MediaQuery\ParamInjectorInterface;
use Ray\MediaQuery\Result\AffectedRows;
use Ray\MediaQuery\Result\InsertedRow;
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
use function is_a;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_subclass_of;
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
    private ExtendedPdo|null $placeholderPdo = null;

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

        $this->assertSupported($method, $dbQuery, $returnType);
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
            if ($this->hasFile($dbQuery, '.json')) {
                return $this->hydrator->hydrate($this->readJsonRow($dbQuery), $entityClass, true, $dbQuery);
            }

            $data = $this->readJsonlRow($dbQuery, $values);
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

    private function assertSupported(ReflectionMethod $method, DbQuery $dbQuery, ReflectionType|null $returnType): void
    {
        if ($method->getAnnotation(Pager::class) instanceof Pager || $this->returns($returnType, PagesInterface::class)) {
            throw new UnsupportedQueryException($dbQuery->id, $method->name, '#[Pager] / PagesInterface results');
        }

        if ($this->returns($returnType, AffectedRows::class) || $this->returns($returnType, InsertedRow::class)) {
            throw new UnsupportedQueryException($dbQuery->id, $method->name, 'DML metadata results (AffectedRows, InsertedRow)');
        }
    }

    /** @param class-string $class */
    private function returns(ReflectionType|null $returnType, string $class): bool
    {
        return $returnType instanceof ReflectionNamedType && is_a($returnType->getName(), $class, true);
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
        $rows = $this->hydrator->hydrate($this->readRows($dbQuery, $values), $entityClass, false, $dbQuery);
        assert(is_array($rows));

        // fromContext() needs a statement and a connection; an in-memory SQLite one stands in for the database.
        $pdo = $this->placeholderPdo ??= new ExtendedPdo('sqlite::memory:');
        $statement = $pdo->query('SELECT 1');
        assert($statement instanceof PDOStatement);

        return $postQueryClass::fromContext(new PostQueryContext($statement, $pdo, $values, $rows));
    }

    /** @return JsonRow|null */
    private function readJsonRow(DbQuery $dbQuery): array|null
    {
        $file = $this->fixtureFile($dbQuery, '.json');
        $row = $this->decodeRow($this->readContent($file), $file, true);

        return $row === null ? null : $this->stripParams($row);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return JsonRow|null
     */
    private function readJsonlRow(DbQuery $dbQuery, array $values): array|null
    {
        if (! $this->hasFile($dbQuery, '.jsonl')) {
            return null;
        }

        $rows = $this->selectRows($this->readJsonl($dbQuery), $values);
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
        $rows = $this->readJsonl($dbQuery);

        $selected = array_map(
            fn (array $row): array => $this->stripParams($row),
            $this->selectRows($rows, $values),
        );

        return $this->applyWindow($selected, $values);
    }

    private function fixtureFile(DbQuery $dbQuery, string $ext): string
    {
        $file = $this->findFile($dbQuery, $ext);
        if ($file === null) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir);
        }

        return $file;
    }

    private function readContent(string $file): string
    {
        $content = file_get_contents($file);
        // file_get_contents() fails only if the file vanished or became unreadable after findFile().
        if ($content === false) {
            throw new FakeJsonNotFoundException($file, $this->config->fakeDir); // @codeCoverageIgnore
        }

        return $content;
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
        return is_bool($value) ? (int) $value : $value;
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
    private function readJsonl(DbQuery $dbQuery): array
    {
        $file = $this->fixtureFile($dbQuery, '.jsonl');
        $rows = [];
        foreach (explode("\n", $this->readContent($file)) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = $this->decodeRow($line, $file . ' line ' . ($index + 1), false);
        }

        return $rows;
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
