<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Override;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Ray\Di\AbstractModule;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Queries;
use Ray\MediaQuery\ReturnEntity;
use Ray\MediaQuery\ReturnEntityInterface;

final class FakeQueryModule extends AbstractModule
{
    public function __construct(
        private readonly string $fakeDir,
        private readonly string $interfaceDir,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    #[Override]
    protected function configure(): void
    {
        $this->bind(FakeQueryConfig::class)->toInstance(new FakeQueryConfig($this->fakeDir));
        $this->bind(DocBlockFactoryInterface::class)->toInstance(DocBlockFactory::createInstance());
        $this->bind(ReturnEntityInterface::class)->to(ReturnEntity::class);

        $queries = Queries::fromDir($this->interfaceDir);
        foreach ($queries->classes as $class) {
            $this->bind($class)->toNull();
        }

        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(DbQuery::class),
            [FakeQueryInterceptor::class],
        );
    }
}
