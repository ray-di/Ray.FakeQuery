# Ray.FakeQuery

Replace SQL execution with JSON fixtures for testing and frontend development.

## Overview

Ray.FakeQuery is a companion package to [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery) that replaces SQL execution with JSON fixture files — no database required.

```
var/
├── sql/
│   └── todo_item.sql      ← production: SQL executed against DB
└── fake/
    └── todo_item.json     ← test/dev: JSON returned directly
```

The query ID defined in `#[DbQuery('todo_item')]` maps directly to the filename. Switch contexts, switch behavior.

## Why Ray.FakeQuery?

**The same philosophy as BEAR.FakeJson** — JSON files become the contract between teams.

- Frontend development proceeds without a database
- Tests run without SQL or migrations
- Fake data from [Semantic-Ex Method](https://koriym.github.io/blog/2025/08/10/semantic-method-en) becomes test fixtures naturally
- Realistic data, zero infrastructure

## Installation

```bash
composer require ray/fake-query
```

## Usage

Define your interfaces as usual with Ray.MediaQuery:

```php
interface TodoQueryInterface
{
    #[DbQuery('todo_item')]
    public function item(string $todoId): ?TodoEntity;

    #[DbQuery('todo_list')]
    /** @return array<TodoEntity> */
    public function list(string $filterStatus = 'all'): array;
}
```

In production, install `MediaQuerySqlModule`. In tests or frontend development, install `FakeQueryModule`:

```php
// Production
protected function configure(): void
{
    $this->install(new MediaQuerySqlModule($sqlDir, $interfaceDir));
}

// Test / Frontend development
protected function configure(): void
{
    $this->install(new FakeQueryModule($fakeDir, $interfaceDir));
}
```

Create JSON files matching the query ID:

```
var/fake/
├── todo_item.json
├── todo_list.json
├── todo_add.json      (void → empty or {})
└── todo_complete.json
```

```json
// var/fake/todo_item.json
{
    "todoId": "01HVXXXXXX0008",
    "todoTitle": "Beフレームワークのチュートリアルを書く",
    "todoMemo": "ALPSから始めて、JSONスキーマ、Beの実装まで",
    "isCompleted": false,
    "createdAt": "2026-03-02T08:00:00+09:00"
}
```

```json
// var/fake/todo_list.json
[
    {
        "todoId": "01HVXXXXXX0008",
        "todoTitle": "Beフレームワークのチュートリアルを書く",
        "isCompleted": false,
        "createdAt": "2026-03-02T08:00:00+09:00"
    },
    {
        "todoId": "01HVXXXXXX0007",
        "todoTitle": "ALPSプロファイルを設計する",
        "isCompleted": true,
        "createdAt": "2026-03-01T10:30:00+09:00"
    }
]
```

## How It Works

`FakeQueryModule` binds each interface method to a JSON-backed implementation:

1. Scan interfaces annotated with `#[DbQuery]`
2. Map query ID → `{fakeDir}/{queryId}.json`
3. Load JSON and hydrate to the declared return type (Entity, array, null)
4. For `void` methods (Commands), do nothing

The hydration follows the same rules as Ray.MediaQuery:
- Single entity: `?Entity` return type
- Collection: `array<Entity>` return type (PHPDoc)
- Raw array: `array` return type

## Command Interfaces

For write operations (`void` return), `FakeQueryModule` performs no-ops by default:

```php
interface TodoCommandInterface
{
    #[DbQuery('todo_add')]
    public function add(string $todoId, string $todoTitle, ?string $todoMemo, DateTimeInterface $createdAt = null): void;

    #[DbQuery('todo_complete')]
    public function complete(string $todoId): void;

    #[DbQuery('todo_delete')]
    public function delete(string $todoId): void;
}
```

No JSON file needed for void methods. They simply succeed silently.

## Project Structure

```
src/
├── FakeQueryModule.php         Ray.Di module
├── FakeQueryInterceptor.php    Intercepts #[DbQuery] calls
├── JsonHydrator.php            JSON → Entity hydration
└── FakeQueryConfig.php         Configuration (fakeDir, interfaceDir)
```

## Relation to Ray.MediaQuery

```
Ray.MediaQuery   SQL files → DB execution → Entity
Ray.FakeQuery    JSON files → hydration → Entity (same interface)
```

Both implement the same interface contracts. Swap modules, swap behavior.

## Design Decisions

- **File naming**: `{queryId}.json` — direct mapping from `#[DbQuery('queryId')]`
- **Hydration**: Reuses Ray.MediaQuery's hydration logic where possible
- **Commands**: void methods are no-ops (fake commands always succeed)
- **Missing files**: If JSON file not found, throw `FakeJsonNotFoundException` with clear message
- **snake_case → camelCase**: Same automatic conversion as Ray.MediaQuery

## Integration with Be Framework

[Be Framework](https://github.com/be-framework/be-framework) uses Ray.Di for DI. Ray.FakeQuery fits naturally:

```
Phase 1: Be + InMemory / FakeQuery  ← develop domain logic, no DB needed
Phase 2: Be + Ray.MediaQuery        ← add SQL, swap module
Phase 3: BEAR.Sunday + Be           ← HTTP layer wraps domain
```

## License

MIT
