<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Aura\Sql\ExtendedPdoInterface;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ray\AuraSqlModule\Pagerfanta\AuraSqlPagerModule;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\FakeQuery\Entity\TodoEntity;
use Ray\FakeQuery\Query\TodoQueryInterface;
use Ray\MediaQuery\MediaQuerySqlModule;

final class ModuleCompositionTest extends TestCase
{
    private const FAKE_DIR = __DIR__ . '/Fake';
    private const INTERFACE_DIR = __DIR__ . '/Fake/Query';
    private const SQL_DIR = __DIR__ . '/Fake/sql';
    private const TODO_ID = '01HVXXXXXX0008';
    private const FAKE_TITLE = 'Write Be Framework tutorial';

    /** @return array<string, array{Closure(): AbstractModule}> */
    public static function compositions(): array
    {
        return [
            'install prod, then fake' => [static fn (): AbstractModule => self::installAll(self::prod(), self::fake())],
            'install fake, then prod' => [static fn (): AbstractModule => self::installAll(self::fake(), self::prod())],
            'fake wrapping prod' => [static fn (): AbstractModule => new FakeQueryModule(self::FAKE_DIR, self::INTERFACE_DIR, self::prod())],
            'prod overridden by fake' => [
                static function (): AbstractModule {
                    $module = self::prod();
                    $module->override(self::fake());

                    return $module;
                },
            ],
        ];
    }

    /** @param Closure(): AbstractModule $module */
    #[DataProvider('compositions')]
    public function testFakeWinsOverMediaQuery(Closure $module): void
    {
        $query = (new Injector($module(), __DIR__ . '/tmp'))->getInstance(TodoQueryInterface::class);

        $todo = $query->item(self::TODO_ID);

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame(self::FAKE_TITLE, $todo->todoTitle);
    }

    private static function prod(): AbstractModule
    {
        return new MediaQuerySqlModule(self::INTERFACE_DIR, self::SQL_DIR, new class extends AbstractModule {
            protected function configure(): void
            {
                $this->bind(ExtendedPdoInterface::class)->toProvider(SqlitePdoProvider::class);
                $this->install(new AuraSqlPagerModule());
            }
        });
    }

    private static function fake(): AbstractModule
    {
        return new FakeQueryModule(self::FAKE_DIR, self::INTERFACE_DIR);
    }

    private static function installAll(AbstractModule ...$modules): AbstractModule
    {
        return new class ($modules) extends AbstractModule {
            /** @param array<AbstractModule> $modules */
            public function __construct(private readonly array $modules)
            {
                parent::__construct();
            }

            protected function configure(): void
            {
                foreach ($this->modules as $module) {
                    $this->install($module);
                }
            }
        };
    }
}
