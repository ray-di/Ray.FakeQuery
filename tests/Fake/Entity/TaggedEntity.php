<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Entity;

final class TaggedEntity
{
    /** @var list<string> */
    public readonly array $tags;

    public function __construct(
        public readonly string $id,
        string ...$tags,
    ) {
        $this->tags = array_values($tags);
    }
}
