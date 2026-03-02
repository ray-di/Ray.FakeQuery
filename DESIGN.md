# Ray.FakeQuery — Design Document for Implementation

## Goal

Implement `Ray.FakeQuery` as a companion package to `Ray.MediaQuery`.

When `FakeQueryModule` is installed instead of `MediaQuerySqlModule`, all `#[DbQuery]` annotated interface methods return data from JSON fixture files instead of executing SQL.

## Reference

- Ray.MediaQuery source: `/Users/akihito/git/Ray.MediaQuery`
- BEAR.FakeJson (same concept for BEAR.Sunday resources): `https://github.com/bearsunday/BEAR.FakeJson`
- README: `README.md` in this repo

## Package Info

```json
{
    "name": "ray/fake-query",
    "description": "Replace Ray.MediaQuery SQL execution with JSON fixtures",
    "require": {
        "php": "^8.1",
        "ray/di": "^2.18",
        "ray/media-query": "^1.0"
    }
}
```

## Directory Structure

```
src/
├── FakeQueryModule.php
├── FakeQueryConfig.php
├── Interceptor/
│   └── FakeQueryInterceptor.php
└── Hydrator/
    └── FakeJsonHydrator.php
tests/
├── FakeQueryModuleTest.php
└── Fake/
    ├── todo_item.json
    └── todo_list.json
var/fake/     (convention, not in src)
```

## Core Classes

### FakeQueryConfig

Simple value object holding configuration:

```php
final class FakeQueryConfig
{
    public function __construct(
        public readonly string $fakeDir,   // path to JSON fixture directory
    ) {}
}
```

### FakeQueryModule

Ray.Di module. Binds all interfaces that have `#[DbQuery]` methods to a proxy that intercepts calls and returns JSON data.

Should work similarly to how `MediaQuerySqlModule` binds interfaces — scan the interface directory, find all interfaces with `#[DbQuery]` methods, and bind them to fake implementations.

```php
final class FakeQueryModule extends AbstractModule
{
    public function __construct(
        private readonly string $fakeDir,
        private readonly string $interfaceDir,  // same as MediaQuerySqlModule
    ) {}

    protected function configure(): void
    {
        // bind FakeQueryConfig
        // scan interfaceDir for interfaces with #[DbQuery] methods
        // for each interface, bind to a generated fake implementation
        // the fake implementation uses FakeQueryInterceptor
    }
}
```

### FakeQueryInterceptor

Intercepts method calls on `#[DbQuery]` annotated methods:

1. Get the query ID from `#[DbQuery('query_id')]`
2. Determine return type from method signature
3. If return type is `void` → do nothing, return null
4. Load `{fakeDir}/{queryId}.json`
5. If file not found → throw `FakeJsonNotFoundException("{queryId}.json not found in {fakeDir}")`
6. Hydrate JSON to return type and return

```php
final class FakeQueryInterceptor implements MethodInterceptor
{
    public function __construct(
        private readonly FakeQueryConfig $config,
        private readonly FakeJsonHydrator $hydrator,
    ) {}

    public function invoke(MethodInvocation $invocation): mixed
    {
        $method = $invocation->getMethod();
        $dbQuery = $method->getAttributes(DbQuery::class)[0]->newInstance();
        $queryId = $dbQuery->id;  // check actual property name in Ray.MediaQuery

        $returnType = $method->getReturnType();

        // void → no-op
        if ($returnType instanceof \ReflectionNamedType && $returnType->getName() === 'void') {
            return null;
        }

        $jsonFile = $this->config->fakeDir . '/' . $queryId . '.json';

        if (! file_exists($jsonFile)) {
            throw new FakeJsonNotFoundException($queryId, $this->config->fakeDir);
        }

        $data = json_decode(file_get_contents($jsonFile), true);

        return $this->hydrator->hydrate($data, $method);
    }
}
```

### FakeJsonHydrator

Hydrates JSON array to the declared return type:

- `?SomeEntity` → hydrate single object or return null if JSON is null
- `array<SomeEntity>` (from PHPDoc `@return`) → hydrate array of objects
- `array` → return raw array
- snake_case keys → camelCase properties (same as Ray.MediaQuery)

Look at how Ray.MediaQuery handles hydration (`/Users/akihito/git/Ray.MediaQuery/src/`) and reuse or replicate the same logic.

## JSON File Conventions

- Single entity (`?Entity`): `{queryId}.json` — single JSON object
- Collection (`array<Entity>`): `{queryId}.jsonl` — JSON Lines, one object per line
- Nullable: missing file or `null` content returns null for nullable return types
- void methods: no file needed

### Why JSONL for collections?

```jsonl
{"todoId": "01HVXXXXXX0007", "todoTitle": "ALPSプロファイルを設計する", "isCompleted": true}
{"todoId": "01HVXXXXXX0008", "todoTitle": "Beフレームワークのチュートリアルを書く", "isCompleted": false}
```

- Adding a record = adding a line (no array syntax, no trailing comma issues)
- Git diffs are clean
- Each line is independently valid JSON

## Exception

```php
final class FakeJsonNotFoundException extends \RuntimeException
{
    public function __construct(string $queryId, string $fakeDir)
    {
        parent::__construct(
            "Fake JSON file not found: {$queryId}.json in {$fakeDir}"
        );
    }
}
```

## Key Behaviors

1. **Commands are no-ops**: `void` return type → silently succeed
2. **Missing file throws**: Clear error message with queryId and directory
3. **snake_case → camelCase**: Automatic key conversion on hydration
4. **Nullable respected**: `?Entity` with null JSON returns null
5. **Array PHPDoc respected**: `@return array<Entity>` triggers array hydration

## Test Strategy

Write tests that:
1. Install `FakeQueryModule` with a test fake directory
2. Get interface instance from injector
3. Assert that method returns hydrated entity from JSON

```php
final class FakeQueryModuleTest extends TestCase
{
    public function testItemQuery(): void
    {
        $injector = new Injector(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->install(new FakeQueryModule(
                    fakeDir: __DIR__ . '/Fake',
                    interfaceDir: __DIR__ . '/Fake/Interface',
                ));
            }
        });

        $query = $injector->getInstance(TodoQueryInterface::class);
        $todo = $query->item('todo-123');

        $this->assertInstanceOf(TodoEntity::class, $todo);
        $this->assertSame('Beフレームワークのチュートリアルを書く', $todo->todoTitle);
    }
}
```

## Implementation Notes

- Study how `MediaQuerySqlModule` scans interfaces and binds them — replicate the same pattern for fake binding
- The `DbQuery` attribute class lives in `Ray\MediaQuery\Annotation\DbQuery` — import from there
- Use `ray/di` interceptor binding: `$this->bindInterceptor(...)` for methods with `#[DbQuery]`
- PHP 8.1+ only — use readonly properties, enums where appropriate
