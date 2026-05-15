<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Override;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Result\PostQueryInterface;
use Ray\MediaQuery\ReturnEntityInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_filter;
use function array_map;
use function array_values;
use function assert;
use function class_exists;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_subclass_of;
use function json_decode;
use function trim;

use const JSON_THROW_ON_ERROR;

/** @psalm-import-type JsonRowList from Types */
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

        /** @var class-string|null $entityClass */
        $entityClass = ($this->returnEntity)($method);

        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();
            if (class_exists($typeName) && is_subclass_of($typeName, PostQueryInterface::class)) {
                return $this->selectPostQuery($typeName, $dbQuery, $entityClass);
            }
        }

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
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir); // @codeCoverageIgnore — file_exists passed
        }

        /** @psalm-suppress MixedAssignment */
        $data = $isRow
            ? json_decode($content, true, 512, JSON_THROW_ON_ERROR)
            : $this->parseJsonl($content);

        return $this->hydrator->hydrate($data, $entityClass, $isRow, $dbQuery);
    }

    /**
     * @param class-string<PostQueryInterface> $postQueryClass
     * @param class-string|null                $entityClass
     */
    private function selectPostQuery(string $postQueryClass, DbQuery $dbQuery, string|null $entityClass): PostQueryInterface
    {
        $rows = $this->hydrator->hydrate($this->readJsonl($dbQuery), $entityClass, false, $dbQuery);
        assert(is_array($rows));

        return (new ReflectionClass($postQueryClass))->newInstance($rows);
    }

    /** @return JsonRowList */
    private function readJsonl(DbQuery $dbQuery): array
    {
        return $this->parseJsonl($this->readContent($dbQuery, '.jsonl'));
    }

    private function readContent(DbQuery $dbQuery, string $ext): string
    {
        $jsonFile = $this->config->fakeDir . '/' . $dbQuery->id . $ext;
        if (! file_exists($jsonFile)) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir);
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            throw new FakeJsonNotFoundException($dbQuery->id . $ext, $this->config->fakeDir); // @codeCoverageIgnore — file_exists passed
        }

        return $content;
    }

    /** @return JsonRowList */
    private function parseJsonl(string $content): array
    {
        $lines = array_values(array_filter(
            explode("\n", trim($content)),
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

        // Ray.Di normalizes `Entity|null` to `?Entity` (ReflectionNamedType),
        // so ReflectionUnionType is unreachable in practice. Verified via xtrace.
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
