# Ray.FakeQuery

A companion to [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery) that replaces SQL execution with JSON fixture files — no database required. Designed for testing and frontend development.

```text
Ray.MediaQuery   SQL files  → DB execution → Entity
Ray.FakeQuery    JSON files → hydration    → Entity (same interface)
```

## Installation

```bash
composer require ray/fake-query --dev
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
| row / nullable row | `<query_id>.json` | One JSON object, or `null` to return `null`. |
| row list | `<query_id>.jsonl` | One JSON object per line. Blank lines are skipped; an empty file is an empty list. |
| void command | no file | The command succeeds as a no-op. |

Select result wrappers whose return type implements
`Ray\MediaQuery\Result\PostQueryInterface` are built through `fromContext()`, as
in Ray.MediaQuery. The context carries the resolved parameter values and the
fixture rows, hydrated like a list query: into the factory result or the entity
named in the `@return` docblock (for example `@return TodoSelection<TodoEntity>`),
or left as associative arrays when neither is given. Its `statement` and `pdo`
come from an in-memory SQLite connection (hence `ext-pdo_sqlite`) and carry no
query result.

When `#[DbQuery(factory: SomeFactory::class)]` is used, FakeQuery calls the
factory method configured by Ray.MediaQuery (the default method name is
`factory`). Static factory methods are called statically; non-static factory
methods are resolved through the injector. Fixture fields are passed as
positional arguments in JSON object order, matching PDO `FETCH_FUNC` semantics,
so factory fixtures should keep field order aligned with the factory method
parameters.

## Unsupported queries

Pagination (`#[Pager]` or a `PagesInterface` return type) and DML metadata
results (`AffectedRows`, `InsertedRow`) have no fixture format. Calling such a
method throws `Ray\FakeQuery\Exception\UnsupportedQueryException`.

## Errors

All exceptions live in `Ray\FakeQuery\Exception`.

| Exception | When |
|---|---|
| `InvalidFakeDirException` | A fake directory does not exist or is not readable. |
| `UnknownFakeJsonException` | A `.json` / `.jsonl` file matches no `#[DbQuery]` id (checked when the module is configured). |
| `FakeJsonNotFoundException` | A list query has no `.jsonl` file, or a non-nullable row query has no fixture row. |
| `InvalidFakeJsonException` | A fixture is not valid JSON or a row is not an object; the message names the file and, for JSONL, the line. |
| `MissingFakeFieldException` | A row lacks a field for a required, non-nullable constructor parameter. |
| `InvalidFactoryException` | The `factory` class or its public factory method does not exist. |
| `UnsupportedQueryException` | See [Unsupported queries](#unsupported-queries). |

## Hydration differences from Ray.MediaQuery

Ray.FakeQuery matches JSON keys to the entity by name, while Ray.MediaQuery hydrates PDO rows as they come. The results differ when column names and the entity disagree:

| Entity | Ray.MediaQuery | Ray.FakeQuery |
|---|---|---|
| No constructor | Sets properties named exactly like the columns: `user_id` leaves `$userId` empty and creates a dynamic `$user_id` property | Converts each key to camelCase (`user_id` → `$userId`) and ignores keys without a matching property |
| Constructor | Passes the columns positionally, in `SELECT` order | Passes each parameter by name (`userId` or `user_id`); a missing parameter takes its default, or `null` if it is nullable, and otherwise throws `MissingFakeFieldException` |

For example, with `new UserEntity(string $userId, string $userName)`, `SELECT 'Alice' AS user_name, 'u001' AS user_id` gives `userId: 'Alice', userName: 'u001'` in Ray.MediaQuery, while `{"user_name": "Alice", "user_id": "u001"}` gives `userId: 'u001', userName: 'Alice'` in Ray.FakeQuery. Alias columns to the property names (`user_id AS userId`) and select them in constructor order to get the same entity from both.
