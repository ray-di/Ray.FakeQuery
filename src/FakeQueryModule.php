<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

use FilesystemIterator;
use Override;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Ray\FakeQuery\Exception\UnknownFakeJsonException;
use Ray\InputQuery\ToArray;
use Ray\InputQuery\ToArrayInterface;
use Ray\MediaQuery\Annotation\DbQuery;
use Ray\MediaQuery\Annotation\Qualifier\FactoryMethod;
use Ray\MediaQuery\DbQueryInterceptor;
use Ray\MediaQuery\ParamConverter;
use Ray\MediaQuery\ParamConverterInterface;
use Ray\MediaQuery\ParamInjector;
use Ray\MediaQuery\ParamInjectorInterface;
use Ray\MediaQuery\Queries;
use Ray\MediaQuery\ReturnEntity;
use Ray\MediaQuery\ReturnEntityInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function assert;
use function basename;
use function is_array;
use function pathinfo;
use function str_replace;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

/** @psalm-import-type DbQueryIdSet from Types */
final class FakeQueryModule extends AbstractModule
{
    /**
     * @param string|list<string>               $fakeDir
     * @param string|list<class-string>|Queries $interfaceDir
     */
    public function __construct(
        private readonly string|array $fakeDir,
        private readonly string|array|Queries $interfaceDir,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    #[Override]
    protected function configure(): void
    {
        $config = new FakeQueryConfig($this->fakeDir);
        $this->bind(FakeQueryConfig::class)->toInstance($config);
        $this->bind(JsonHydrator::class);
        $this->bind(DocBlockFactoryInterface::class)->toInstance(DocBlockFactory::createInstance());
        $this->bind(ReturnEntityInterface::class)->to(ReturnEntity::class);
        $this->bind(ParamInjectorInterface::class)->to(ParamInjector::class);
        $this->bind(ParamConverterInterface::class)->to(ParamConverter::class);
        $this->bind(ToArrayInterface::class)->to(ToArray::class);
        $this->bind()->annotatedWith(FactoryMethod::class)->toInstance('factory');

        $queries = $this->queries();
        foreach ($queries->classes as $class) {
            $this->bind($class)->toNull();
        }

        $this->validateFakeFiles($queries->classes, $config);

        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(DbQuery::class),
            [DbQueryInterceptor::class],
        );
        $this->bind(DbQueryInterceptor::class)->to(FakeQueryInterceptor::class)->in(Scope::SINGLETON);
    }

    private function queries(): Queries
    {
        if ($this->interfaceDir instanceof Queries) {
            return $this->interfaceDir;
        }

        if (is_array($this->interfaceDir)) {
            return Queries::fromClasses($this->interfaceDir);
        }

        return Queries::fromDir($this->interfaceDir);
    }

    /** @param list<class-string> $classes */
    private function validateFakeFiles(array $classes, FakeQueryConfig $config): void
    {
        $knownIds = $this->collectDbQueryIds($classes);

        foreach ($config->fakeDirs as $fakeDir) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fakeDir, FilesystemIterator::SKIP_DOTS),
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
                $stem = $this->queryIdFromFile($file, $fakeDir, $ext);
                if (! isset($knownIds[$stem])) {
                    throw new UnknownFakeJsonException($basename, $fakeDir);
                }
            }
        }
    }

    private function queryIdFromFile(SplFileInfo $file, string $fakeDir, string $ext): string
    {
        $relative = substr($file->getPathname(), strlen($fakeDir) + 1);
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
