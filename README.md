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
$this->install(new MediaQuerySqlModule($sqlDir, $interfaceDir));

// Test / Frontend development
$this->install(new FakeQueryModule($fakeDir, $interfaceDir));
```

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

JSON keys use `snake_case`; entity properties use `camelCase`. Conversion is automatic, matching Ray.MediaQuery behavior.

## Fixture Format

| Query shape | File | Meaning |
|-------------|------|---------|
| row / nullable row | `<query_id>.json` | One JSON object, raw row, or `null`. |
| row list | `<query_id>.jsonl` | One JSON object per line. Empty files represent empty lists. |
| void command | no file | The command succeeds as a no-op. |
