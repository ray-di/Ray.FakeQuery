<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\MediaQuery\StringCase;
use ReflectionClass;
use ReflectionParameter;

use function array_key_exists;
use function array_map;
use function assert;
use function is_array;
use function method_exists;

/** @psalm-import-type JsonRow from Types */
final class JsonHydrator
{
    /** @param class-string|null $entityClass */
    public function hydrate(mixed $data, string|null $entityClass, bool $isRow): mixed
    {
        if ($isRow) {
            return $this->hydrateRow($data, $entityClass);
        }

        return $this->hydrateRowList($data, $entityClass);
    }

    /** @param class-string|null $entityClass */
    private function hydrateRow(mixed $data, string|null $entityClass): mixed
    {
        if ($data === null) {
            return null;
        }

        if ($entityClass === null) {
            return $data;
        }

        assert(is_array($data));
        /** @var JsonRow $data */

        return $this->hydrateOne($data, $entityClass);
    }

    /** @param class-string|null $entityClass */
    private function hydrateRowList(mixed $data, string|null $entityClass): mixed
    {
        if ($entityClass === null) {
            return $data;
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

        return null;
    }
}
