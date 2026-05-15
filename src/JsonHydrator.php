<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\Di\InjectorInterface;
use Ray\FakeQuery\Exception\InvalidFactoryException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Qualifier\FactoryMethod;
use Ray\MediaQuery\StringCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function class_exists;
use function is_array;
use function method_exists;

/** @psalm-import-type JsonRow from Types */
final class JsonHydrator
{
    public function __construct(
        #[FactoryMethod]
        private readonly string $factoryMethod,
        private readonly InjectorInterface $injector,
    ) {
    }

    /** @param class-string|null $entityClass */
    public function hydrate(mixed $data, string|null $entityClass, bool $isRow, DbQuery|null $dbQuery = null): mixed
    {
        if ($isRow) {
            return $this->hydrateRow($data, $entityClass, $dbQuery);
        }

        return $this->hydrateRowList($data, $entityClass, $dbQuery);
    }

    /** @param class-string|null $entityClass */
    private function hydrateRow(mixed $data, string|null $entityClass, DbQuery|null $dbQuery): mixed
    {
        if ($data === null) {
            return null; // @codeCoverageIgnore json_decode returns null only for "null" content
        }

        $factory = $this->factory($dbQuery);
        if ($factory !== null) {
            assert(is_array($data));
            /** @var JsonRow $data */

            return $factory($data);
        }

        if ($entityClass === null) {
            return $data;
        }

        assert(is_array($data));
        /** @var JsonRow $data */

        return $this->hydrateOne($data, $entityClass);
    }

    /** @param class-string|null $entityClass */
    private function hydrateRowList(mixed $data, string|null $entityClass, DbQuery|null $dbQuery): mixed
    {
        $factory = $this->factory($dbQuery);
        if ($factory !== null) {
            assert(is_array($data));

            return array_map(
                static function (mixed $row) use ($factory): mixed {
                    assert(is_array($row));
                    /** @var JsonRow $row */

                    return $factory($row);
                },
                $data,
            );
        }

        if ($entityClass === null) {
            return $data; // @codeCoverageIgnore ReturnEntity always resolves entity for @return array<Entity>
        }

        assert(is_array($data));

        return array_map(
            function (mixed $row) use ($entityClass): object {
                assert(is_array($row));
                /** @var JsonRow $row */

                return $this->hydrateOne($row, $entityClass);
            },
            $data,
        );
    }

    /** @return (callable(JsonRow): mixed)|null */
    private function factory(DbQuery|null $dbQuery): callable|null
    {
        if ($dbQuery === null || $dbQuery->factory === '') {
            return null;
        }

        $factoryClass = $dbQuery->factory;
        $factoryMethod = $this->factoryMethod;
        if (! class_exists($factoryClass) || ! method_exists($factoryClass, $factoryMethod)) {
            throw new InvalidFactoryException($factoryClass, $factoryMethod);
        }

        $method = new ReflectionMethod($factoryClass, $factoryMethod);
        if (! $method->isPublic()) {
            throw new InvalidFactoryException($factoryClass, $factoryMethod);
        }

        if ($method->isStatic()) {
            return static fn (array $row): mixed => $method->invokeArgs(null, array_values($row));
        }

        $factory = $this->injector->getInstance($factoryClass);

        return static fn (array $row): mixed => $method->invokeArgs($factory, array_values($row));
    }

    /**
     * @param JsonRow      $row
     * @param class-string $entityClass
     */
    private function hydrateOne(array $row, string $entityClass): object
    {
        $refClass = new ReflectionClass($entityClass);

        if (! method_exists($entityClass, '__construct')) {
            return $this->hydrateProperties($refClass, $row);
        }

        return $this->hydrateConstructor($refClass, $row);
    }

    /**
     * @param ReflectionClass<object> $refClass
     * @param JsonRow                 $row
     */
    private function hydrateProperties(ReflectionClass $refClass, array $row): object
    {
        $obj = $refClass->newInstanceWithoutConstructor();
        /** @psalm-suppress MixedAssignment */
        foreach ($row as $key => $value) {
            $propName = StringCase::camel($key);
            if ($refClass->hasProperty($propName)) {
                $refClass->getProperty($propName)->setValue($obj, $value);
            }
        }

        return $obj;
    }

    /**
     * @param ReflectionClass<object> $refClass
     * @param JsonRow                 $row
     */
    private function hydrateConstructor(ReflectionClass $refClass, array $row): object
    {
        $constructor = $refClass->getConstructor();
        assert($constructor !== null);

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            /** @psalm-suppress MixedAssignment */
            $args[] = $this->resolveArgument($param, $row);
        }

        return $refClass->newInstance(...$args);
    }

    /** @param JsonRow $row */
    private function resolveArgument(ReflectionParameter $param, array $row): mixed
    {
        $name = $param->getName();
        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        $snake = StringCase::snake($name);
        if (array_key_exists($snake, $row)) {
            return $row[$snake];
        }

        if ($param->isOptional()) {
            return $param->getDefaultValue();
        }

        return null; // @codeCoverageIgnore all constructor params should match JSON keys or be optional
    }
}
