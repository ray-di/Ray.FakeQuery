<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Entity;

final class NoteEntity
{
    public function __construct(
        public readonly string $noteId,
        public readonly string|null $noteBody,
    ) {
    }
}
