<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Ray\FakeQuery\Exception\InvalidFakeDirException;

use function implode;
use function is_dir;
use function is_readable;
use function is_string;
use function rtrim;

use const PATH_SEPARATOR;

final class FakeQueryConfig
{
    /** @var list<string> */
    public readonly array $fakeDirs;
    public readonly string $fakeDir;

    /** @param string|list<string> $fakeDir */
    public function __construct(string|array $fakeDir)
    {
        $fakeDirs = is_string($fakeDir) ? [$fakeDir] : $fakeDir;
        $normalized = [];
        foreach ($fakeDirs as $dir) {
            $normalizedDir = rtrim($dir, '/\\');
            if (! is_dir($normalizedDir) || ! is_readable($normalizedDir)) {
                throw new InvalidFakeDirException($dir);
            }

            $normalized[] = $normalizedDir;
        }

        $this->fakeDirs = $normalized;
        $this->fakeDir = implode(PATH_SEPARATOR, $normalized);
    }
}
