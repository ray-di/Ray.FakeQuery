<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use Override;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Ray\Di\AbstractModule;
use Ray\FakeQuery\Exception\UnknownFakeJsonException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Queries;
use Ray\MediaQuery\ReturnEntity;
use Ray\MediaQuery\ReturnEntityInterface;
use ReflectionClass;

use function basename;
use function glob;
use function pathinfo;

use const GLOB_BRACE;
use const GLOB_NOSORT;
use const PATHINFO_EXTENSION;

/** @psalm-import-type DbQueryIdSet from Types */
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
        $this->bind(JsonHydrator::class);
        $this->bind(DocBlockFactoryInterface::class)->toInstance(DocBlockFactory::createInstance());
        $this->bind(ReturnEntityInterface::class)->to(ReturnEntity::class);

        $queries = Queries::fromDir($this->interfaceDir);
        foreach ($queries->classes as $class) {
            $this->bind($class)->toNull();
        }

        $this->validateFakeFiles($queries->classes);

        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(DbQuery::class),
            [FakeQueryInterceptor::class],
        );
    }

    /** @param list<class-string> $classes */
    private function validateFakeFiles(array $classes): void
    {
        $knownIds = $this->collectDbQueryIds($classes);

        $globResult = glob($this->fakeDir . '/*.{json,jsonl}', GLOB_NOSORT | GLOB_BRACE);
        $files = $globResult === false ? [] : $globResult;
        foreach ($files as $file) {
            $basename = basename($file);
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $stem = $ext === 'jsonl' ? basename($file, '.jsonl') : basename($file, '.json');
            if (! isset($knownIds[$stem])) {
                throw new UnknownFakeJsonException($basename, $this->fakeDir);
            }
        }
    }

    /**
     * @param list<class-string> $classes
     *
     * @return DbQueryIdSet
     */
    private function collectDbQueryIds(array $classes): array
    {
        $ids = [];
        foreach ($classes as $class) {
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods() as $method) {
                $attrs = $method->getAttributes(DbQuery::class);
                foreach ($attrs as $attr) {
                    /** @var DbQuery $instance */
                    $instance = $attr->newInstance();
                    $ids[$instance->id] = true;
                }
            }
        }

        return $ids;
    }
}
