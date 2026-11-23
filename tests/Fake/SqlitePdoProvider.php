<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use Ray\Di\ProviderInterface;

/**
 * Loads aura/sql only when real SQL runs: aura/sql 5, picked by lowest-dependency installs, cannot load on PHP 8.4+
 *
 * @implements ProviderInterface<ExtendedPdoInterface>
 */
final class SqlitePdoProvider implements ProviderInterface
{
    public function get(): ExtendedPdoInterface
    {
        return new ExtendedPdo('sqlite::memory:');
    }
}
