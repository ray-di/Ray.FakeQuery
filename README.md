# Ray.FakeQuery

A companion to [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery) that replaces SQL execution with JSON fixture files — no database required. Designed for testing and frontend development.

```text
Ray.MediaQuery   SQL files  → DB execution → Entity
Ray.FakeQuery    JSON files → hydration    → Entity (same interface)
```

## Installation

```bash
composer require ray/fake-query:^1.0 --dev
```

This package targets Ray.MediaQuery `^1.1`.

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

`var/fake/todo_list.jsonl` — collection (`array<Entity>`), one object per line:

```jsonl
{"todo_id": "01HVXXXXXX0008", "todo_title": "Buy groceries"}
{"todo_id": "01HVXXXXXX0007", "todo_title": "Call dentist"}
```

`void` methods require no file — they succeed silently as no-ops.

JSON keys may be `snake_case` or `camelCase`; both map to `camelCase` entity properties.

## Fixture Format

| Query shape | File | Meaning |
|-------------|------|---------|
| row / nullable row | `<query_id>.json` | One JSON object, raw row, or `null`. |
| row list | `<query_id>.jsonl` | One JSON object per line. Empty files represent empty lists. |
| void command | no file | The command succeeds as a no-op. |

Constructor-based select result wrappers are supported for return types that
implement `Ray\MediaQuery\Result\PostQueryInterface`, such as a typed selection
object wrapping hydrated rows. FakeQuery passes the hydrated row list to the
wrapper constructor; it does not call `PostQueryInterface::fromContext()`.

When `#[DbQuery(factory: SomeFactory::class)]` is used, FakeQuery calls the
factory method configured by Ray.MediaQuery (the default method name is
`factory`). Static factory methods are called statically; non-static factory
methods are resolved through the injector. Fixture fields are passed as
positional arguments in JSON object order, matching PDO `FETCH_FUNC` semantics,
so factory fixtures should keep field order aligned with the factory method
parameters.

DML metadata results such as `AffectedRows` and `InsertedRow` are outside the
current select-fixture scope.

## Hydration differences from Ray.MediaQuery

Ray.FakeQuery matches JSON keys to the entity by name, while Ray.MediaQuery hydrates PDO rows as they come. The results differ when column names and the entity disagree:

| Entity | Ray.MediaQuery | Ray.FakeQuery |
|---|---|---|
| No constructor | Sets properties named exactly like the columns: `user_id` leaves `$userId` empty and creates a dynamic `$user_id` property | Converts each key to camelCase (`user_id` → `$userId`) and ignores keys without a matching property |
| Constructor | Passes the columns positionally, in `SELECT` order | Passes each parameter by name (`userId` or `user_id`); a missing optional parameter takes its default |

For example, with `new UserEntity(string $userId, string $userName)`, `SELECT 'Alice' AS user_name, 'u001' AS user_id` gives `userId: 'Alice', userName: 'u001'` in Ray.MediaQuery, while `{"user_name": "Alice", "user_id": "u001"}` gives `userId: 'u001', userName: 'Alice'` in Ray.FakeQuery. Alias columns to the property names (`user_id AS userId`) and select them in constructor order to get the same entity from both.
