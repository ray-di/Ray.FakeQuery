# Ray.FakeQuery

A companion to [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery) that replaces SQL execution with JSON fixture files — no database required. Designed for testing and frontend development.

```text
Ray.MediaQuery   SQL files  → DB execution → Entity
Ray.FakeQuery    JSON files → hydration    → Entity (same interface)
```

## Installation

```bash
composer require ray/fake-query 1.x-dev --dev
```

## Usage

Define query interfaces with `#[DbQuery]` as usual:

```php
interface TodoQueryInterface
{
    #[DbQuery('todo_item')]
    public function item(string $todoId): ?TodoEntity;

    #[DbQuery('todo_list')]
    /** @return array<TodoEntity> */
    public function list(string $filterStatus = 'all'): array;

    #[DbQuery('todo_add')]
    public function add(string $todoId, string $title): void;
}
```

Swap modules to switch between real SQL and fake JSON:

```php
// Production
$this->install(new MediaQuerySqlModule($interfaceDir, $sqlDir));

// Test / Frontend development
$this->install(new FakeQueryModule($fakeDir, $interfaceDir));
```

When both modules end up in one injector, `FakeQueryModule` takes over `#[DbQuery]` methods whatever the install order, including `override()`.

Create JSON files matching the query ID in `#[DbQuery]`:

`var/fake/todo_item.json` — single entity (`?Entity`):

```json
{
    "todo_id": "01HVXXXXXX0008",
    "todo_title": "Buy groceries"
}
```

A `.json` file containing `null` returns `null` from a nullable row method.

`var/fake/todo_list.jsonl` — collection (`array<Entity>`), one object per line:

```jsonl
{"todo_id": "01HVXXXXXX0008", "todo_title": "Buy groceries"}
{"todo_id": "01HVXXXXXX0007", "todo_title": "Call dentist"}
```

Blank lines are skipped; an empty file is an empty list.

`void` methods require no file — they succeed silently as no-ops.

JSON keys may be `snake_case` or `camelCase`; both map to `camelCase` entity properties.

## Hydration differences from Ray.MediaQuery

Ray.FakeQuery matches JSON keys to the entity by name, while Ray.MediaQuery hydrates PDO rows as they come. The results differ when column names and the entity disagree:

| Entity | Ray.MediaQuery | Ray.FakeQuery |
|---|---|---|
| No constructor | Sets properties named exactly like the columns: `user_id` leaves `$userId` empty and creates a dynamic `$user_id` property | Converts each key to camelCase (`user_id` → `$userId`) and ignores keys without a matching property |
| Constructor | Passes the columns positionally, in `SELECT` order | Passes each parameter by name (`userId` or `user_id`); a missing optional parameter takes its default, a missing nullable one gets `null`, any other throws `MissingFakeFieldException` |
| `#[DbQuery(factory: ...)]` | Calls the static `factory()` statically, otherwise on an instance from the injector, passing the columns positionally | Same, passing the fixture fields positionally in object order |

For example, with `new UserEntity(string $userId, string $userName)`, `SELECT 'Alice' AS user_name, 'u001' AS user_id` gives `userId: 'Alice', userName: 'u001'` in Ray.MediaQuery, while `{"user_name": "Alice", "user_id": "u001"}` gives `userId: 'u001', userName: 'Alice'` in Ray.FakeQuery. Alias columns to the property names (`user_id AS userId`) and select them in constructor order to get the same entity from both.

## Errors

| Exception | Thrown when |
|---|---|
| `InvalidFakeDirException` | The fake directory does not exist or is not readable |
| `FakeJsonNotFoundException` | The fixture for a non-nullable row or a row list does not exist |
| `UnknownFakeJsonException` | A fixture file matches no `#[DbQuery]` id (on module install) |
| `InvalidFakeJsonException` | A fixture is not valid JSON, or a row is not a JSON object (`null` is allowed for a `.json` row); the message names the file and the JSONL line |
| `MissingFakeFieldException` | A required constructor or factory parameter has no field in the row |
| `InvalidFactoryException` | The `factory` class or its `factory()` method does not exist or is not public |
| `UnsupportedQueryException` | The query needs a database result that a fixture cannot provide (see below) |

## Unsupported queries

These throw `UnsupportedQueryException` instead of returning a fixture:

- `#[Pager]` methods and `PagesInterface` return types
- `PostQueryInterface` return types, including `AffectedRows` and `InsertedRow`
