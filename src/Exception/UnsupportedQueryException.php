<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Exception;

final class UnsupportedQueryException extends LogicException
{
    public function __construct(string $queryId, string $method, string $feature)
    {
        parent::__construct("Ray.FakeQuery does not support {$feature}: #[DbQuery('{$queryId}')] {$method}()");
    }
}
