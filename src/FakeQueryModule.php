<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use FilesystemIterator;
use Override;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Ray\Di\AbstractModule;
use Ray\FakeQuery\Exception\UnknownFakeJsonException;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Qualifier\FactoryMethod;
use Ray\MediaQuery\Queries;
use Ray\MediaQuery\ReturnEntity;
use Ray\MediaQuery\ReturnEntityInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function assert;
use function basename;
use function pathinfo;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
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
        $this->bind()->annotatedWith(FactoryMethod::class)->toInstance('factory');

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

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fakeDir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            if (! $file->isFile()) {
                continue;
            }

            $ext = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
            if ($ext !== 'json' && $ext !== 'jsonl') {
                continue;
            }

            $basename = basename($file->getPathname());
            $stem = $this->queryIdFromFile($file, $ext);
            if (! isset($knownIds[$stem])) {
                throw new UnknownFakeJsonException($basename, $this->fakeDir);
            }
        }
    }

    private function queryIdFromFile(SplFileInfo $file, string $ext): string
    {
        $relative = substr($file->getPathname(), strlen($this->fakeDir) + 1);
        $queryId = substr($relative, 0, -strlen('.' . $ext));

        return str_replace(DIRECTORY_SEPARATOR, '/', $queryId);
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
