<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Closure;
use Ray\Di\InjectorInterface;
use Ray\FakeQuery\Exception\InvalidFactoryException;
use Ray\FakeQuery\Exception\MissingFakeFieldException;
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

/**
 * @psalm-import-type JsonRow from Types
 * @psalm-import-type JsonRowList from Types
 */
final class JsonHydrator
{
    public function __construct(
        private readonly InjectorInterface $injector,
        #[FactoryMethod]
        private readonly string $factoryMethod = 'factory',
    ) {
    }

    /**
     * @param class-string|null $entityClass
     * @param string            $factoryClass `#[DbQuery(factory: ...)]`; empty when none is configured
     */
    public function hydrate(mixed $data, string|null $entityClass, bool $isRow, string $factoryClass = ''): mixed
    {
        if ($data === null) {
            return null;
        }

        assert(is_array($data));
        $factory = $this->factoryHydrator($factoryClass);
        if ($isRow) {
            /** @var JsonRow $data */
            return $this->hydrateRow($data, $factory, $entityClass);
        }

        /** @var JsonRowList $data */
        return array_map(fn (array $row): mixed => $this->hydrateRow($row, $factory, $entityClass), $data);
    }

    /**
     * @param JsonRow                        $row
     * @param (Closure(JsonRow): mixed)|null $factory
     * @param class-string|null              $entityClass
     */
    private function hydrateRow(array $row, Closure|null $factory, string|null $entityClass): mixed
    {
        if ($factory !== null) {
            return $factory($row);
        }

        if ($entityClass === null) {
            return $row;
        }

        return $this->hydrateOne($row, $entityClass);
    }

    /**
     * A static factory method is called statically, any other on an instance from the injector, as
     * Ray.MediaQuery does; fixture fields are passed positionally in object order, like PDO::FETCH_FUNC.
     *
     * @return (Closure(JsonRow): mixed)|null
     */
    private function factoryHydrator(string $factoryClass): Closure|null
    {
        if ($factoryClass === '') {
            return null;
        }

        $method = $this->factoryMethod;
        if (! class_exists($factoryClass) || ! method_exists($factoryClass, $method)) {
            throw new InvalidFactoryException($factoryClass, $method);
        }

        $reflection = new ReflectionMethod($factoryClass, $method);
        if (! $reflection->isPublic()) {
            throw new InvalidFactoryException($factoryClass, $method);
        }

        $factory = $reflection->isStatic() ? null : $this->injector->getInstance($factoryClass);
        $callee = "{$factoryClass}::{$method}()";

        return static function (array $row) use ($reflection, $factory, $callee): mixed {
            $args = array_values($row);
            foreach ($reflection->getParameters() as $position => $param) {
                if (! array_key_exists($position, $args) && ! $param->isOptional()) {
                    throw new MissingFakeFieldException($callee, $param->getName(), 'field at position ' . ($position + 1));
                }
            }

            return $reflection->invokeArgs($factory, $args);
        };
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
            $propName = StringCase::camel((string) $key);
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

        $callee = $refClass->getName() . '::__construct()';
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            if ($param->isVariadic()) {
                $args = [...$args, ...$this->variadicArguments($param, $row)];
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $args[] = $this->resolveArgument($param, $row, $callee);
        }

        return $refClass->newInstance(...$args);
    }

    /** @param JsonRow $row */
    private function resolveArgument(ReflectionParameter $param, array $row, string $callee): mixed
    {
        $name = $param->getName();
        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        $snake = StringCase::snake($name);
        if (array_key_exists($snake, $row)) {
            return $row[$snake];
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw new MissingFakeFieldException($callee, $name, "\"{$name}\" or \"{$snake}\" field");
    }

    /**
     * @param JsonRow $row
     *
     * @return list<mixed>
     */
    private function variadicArguments(ReflectionParameter $param, array $row): array
    {
        foreach ([$param->getName(), StringCase::snake($param->getName())] as $field) {
            if (array_key_exists($field, $row)) {
                return is_array($row[$field]) ? array_values($row[$field]) : [$row[$field]];
            }
        }

        return [];
    }
}
