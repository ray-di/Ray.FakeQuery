<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Override;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\FakeQuery\Exception\FakeJsonNotFoundException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\ReturnEntityInterface;
use ReflectionNamedType;
use ReflectionUnionType;

use function assert;
use function file_exists;
use function file_get_contents;
use function json_decode;

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

        $isRow = $dbQuery->type === 'row'
            || $returnType instanceof ReflectionUnionType
            || ($returnType instanceof ReflectionNamedType && $returnType->getName() !== 'array');

        $jsonFile = $this->config->fakeDir . '/' . $dbQuery->id . '.json';
        if (! file_exists($jsonFile)) {
            throw new FakeJsonNotFoundException($dbQuery->id, $this->config->fakeDir);
        }

        /** @psalm-suppress MixedAssignment */
        $data = json_decode((string) file_get_contents($jsonFile), true);
        $entityClass = ($this->returnEntity)($method);

        return $this->hydrator->hydrate($data, $entityClass, $isRow);
    }
}
