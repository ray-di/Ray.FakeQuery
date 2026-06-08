<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Aura\Sql\ExtendedPdoInterface;
use Aura\Sql\Parser\ParserInterface;
use Aura\Sql\Profiler\ProfilerInterface;
use Generator;
use LogicException;
use Override;
use PDO;
use PDOStatement;
use stdClass;

use function array_filter;
use function array_key_first;
use function array_map;
use function assert;
use function class_exists;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;
use function str_replace;

/**
 * Adapter used only to satisfy PostQueryContext in fake mode.
 *
 * @internal
 * @psalm-suppress MixedAssignment
 */
final class FakeQueryPdo implements ExtendedPdoInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    #[Override]
    public function connect(): void
    {
    }

    #[Override]
    public function disconnect(): void
    {
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function fetchAffected(string $statement, array $values = []): int
    {
        return $this->perform($statement, $values)->rowCount();
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchAll(string $statement, array $values = []): array
    {
        return $this->perform($statement, $values)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchAssoc(string $statement, array $values = []): array
    {
        $rows = [];
        foreach ($this->fetchAll($statement, $values) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $firstKey = array_key_first($row);
            if ($firstKey === null) {
                continue;
            }

            $first = $row[$firstKey];
            $key = is_scalar($first) ? (string) $first : '';
            $rows[$key] = $row;
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchCol(string $statement, array $values = []): array
    {
        return $this->perform($statement, $values)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchGroup(string $statement, array $values = [], int $style = PDO::FETCH_COLUMN): array
    {
        return $this->perform($statement, $values)->fetchAll(PDO::FETCH_GROUP | $style);
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $args
     */
    #[Override]
    public function fetchObject(
        string $statement,
        array $values = [],
        string $class = stdClass::class,
        array $args = [],
    ): object|false {
        if (! class_exists($class)) {
            return false;
        }

        return $this->perform($statement, $values)->fetchObject($class, $args);
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $args
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchObjects(
        string $statement,
        array $values = [],
        string $class = stdClass::class,
        array $args = [],
    ): array {
        if (! class_exists($class)) {
            return [];
        }

        return $this->perform($statement, $values)->fetchAll(PDO::FETCH_CLASS, $class);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>|false
     */
    #[Override]
    public function fetchOne(string $statement, array $values = []): array|false
    {
        $row = $this->perform($statement, $values)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : false;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fetchPairs(string $statement, array $values = []): array
    {
        return $this->perform($statement, $values)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function fetchValue(string $statement, array $values = []): mixed
    {
        return $this->perform($statement, $values)->fetchColumn();
    }

    #[Override]
    public function getParser(): ParserInterface
    {
        throw new LogicException('FakeQueryPdo does not provide a SQL parser.');
    }

    #[Override]
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    #[Override]
    public function getProfiler(): ProfilerInterface
    {
        throw new LogicException('FakeQueryPdo does not provide a SQL profiler.');
    }

    #[Override]
    public function quoteName(string $name): string
    {
        return implode('.', array_map($this->quoteSingleName(...), explode('.', $name)));
    }

    #[Override]
    public function quoteSingleName(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    #[Override]
    public function isConnected(): bool
    {
        return true;
    }

    #[Override]
    public function setParser(ParserInterface $parser): void
    {
    }

    #[Override]
    public function setProfiler(ProfilerInterface $profiler): void
    {
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function yieldAll(string $statement, array $values = []): Generator
    {
        $statement = $this->perform($statement, $values);
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function yieldAssoc(string $statement, array $values = []): Generator
    {
        foreach ($this->fetchAssoc($statement, $values) as $key => $row) {
            yield $key => $row;
        }
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function yieldCol(string $statement, array $values = []): Generator
    {
        foreach ($this->fetchCol($statement, $values) as $value) {
            yield $value;
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $args
     */
    #[Override]
    public function yieldObjects(
        string $statement,
        array $values = [],
        string $class = stdClass::class,
        array $args = [],
    ): Generator {
        foreach ($this->fetchObjects($statement, $values, $class, $args) as $object) {
            yield $object;
        }
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function yieldPairs(string $statement, array $values = []): Generator
    {
        foreach ($this->fetchPairs($statement, $values) as $key => $value) {
            yield $key => $value;
        }
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function perform(string $statement, array $values = []): PDOStatement
    {
        $sth = $this->prepareWithValues($statement, $values);
        $sth->execute();

        return $sth;
    }

    /** @param array<array-key, mixed> $values */
    #[Override]
    public function prepareWithValues(string $statement, array $values = []): PDOStatement
    {
        $sth = $this->pdo->prepare($statement);
        assert($sth instanceof PDOStatement);
        foreach ($values as $key => $value) {
            $sth->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, $this->paramType($value));
        }

        return $sth;
    }

    #[Override]
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    #[Override]
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    #[Override]
    public function errorCode(): string|null
    {
        return $this->pdo->errorCode();
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public function errorInfo(): array
    {
        return $this->pdo->errorInfo();
    }

    #[Override]
    public function exec(string $statement): int|false
    {
        return $this->pdo->exec($statement);
    }

    /** @return bool|int|string|array<array-key, mixed>|null */
    #[Override]
    public function getAttribute(int $attribute): bool|int|string|array|null
    {
        $value = $this->pdo->getAttribute($attribute);
        if (is_array($value) || is_bool($value) || is_int($value) || is_string($value) || $value === null) {
            return $value;
        }

        throw new LogicException('Unsupported PDO attribute value.');
    }

    #[Override]
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    #[Override]
    public function lastInsertId(string|null $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    /** @param array<array-key, mixed> $options */
    #[Override]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return $this->pdo->prepare($query, $options);
    }

    /** @psalm-suppress ParamNameMismatch */
    #[Override]
    public function query(string $query, int|null $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($fetchMode === null) {
            return $this->pdo->query($query);
        }

        return $this->pdo->query($query, $fetchMode, ...$fetchModeArgs);
    }

    /** @param string|int|array<array-key, mixed>|float|null $value */
    #[Override]
    public function quote(string|int|array|float|null $value, int $type = PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($this->quoteValueToString($value), $type);
    }

    #[Override]
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    #[Override]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /** @return array<array-key, mixed> */
    #[Override]
    public static function getAvailableDrivers(): array
    {
        return array_filter(PDO::getAvailableDrivers(), is_string(...));
    }

    private function quoteValueToString(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        return implode(',', array_map($this->quoteValueToString(...), $value));
    }

    private function paramType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            default => PDO::PARAM_STR,
        };
    }
}
