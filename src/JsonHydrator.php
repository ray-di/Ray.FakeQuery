<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\MediaQuery\StringCase;
use ReflectionClass;

use function array_key_exists;
use function array_map;
use function assert;
use function is_array;
use function method_exists;

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
        /** @var array<string, mixed> $data */

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
                /** @var array<string, mixed> $row */

                return $this->hydrateOne($row, $entityClass);
            },
            $data,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param class-string         $entityClass
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
     * @param array<string, mixed>    $row
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
     * @param array<string, mixed>    $row
     */
    private function hydrateConstructor(ReflectionClass $refClass, array $row): object
    {
        $constructor = $refClass->getConstructor();
        assert($constructor !== null);

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $snake = StringCase::snake($name);
            /** @psalm-suppress MixedAssignment */
            if (array_key_exists($name, $row)) {
                $args[] = $row[$name];
            } elseif (array_key_exists($snake, $row)) {
                $args[] = $row[$snake];
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $refClass->newInstance(...$args);
    }
}
