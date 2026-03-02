<?php

declare(strict_types=1);

namespace Ray\FakeQuery\Query;

use Ray\FakeQuery\Entity\UserEntity;
use Ray\MediaQuery\Annotation\DbQuery;

interface UserQueryInterface
{
    #[DbQuery('user_item')]
    public function item(string $userId): UserEntity|null;

    /** @return array<UserEntity> */
    #[DbQuery('user_list')]
    public function list(): array;
}
