# Ray.FakeQuery Design Notes

Ray.FakeQuery is a fixture adapter for Ray.MediaQuery query interfaces. It keeps
the application-facing query interface unchanged while replacing SQL execution
with JSON or JSONL fixture data.

## Scope

The 1.0 scope is intentionally limited to select-style fake responses:

- row and nullable row responses from `<query_id>.json`
- row list responses from `<query_id>.jsonl`
- `void` command methods as no-op methods
- constructor-based `PostQueryInterface` result wrappers
- static and injected factory hydration via `#[DbQuery(factory: ...)]`

DML metadata result objects such as `AffectedRows` and `InsertedRow` are tracked
separately for a later release. They should remain fixture-driven and must not
turn FakeQuery into a fake PDO or mutable in-memory database.

## Module Replacement

`FakeQueryModule` binds all Ray.MediaQuery query interfaces from the configured
interface directory to null-object proxies, the same proxy surface used by
Ray.MediaQuery.

For override use, FakeQuery does not compete with MediaQuery pointcuts by
priority. It binds the MediaQuery interceptor token itself:

```php
$this->bindInterceptor(
    $this->matcher->any(),
    $this->matcher->annotatedWith(DbQuery::class),
    [DbQueryInterceptor::class],
);
$this->bind(DbQueryInterceptor::class)->to(FakeQueryInterceptor::class);
```

If an application already installed `MediaQueryModule`, the existing `#[DbQuery]`
pointcut may remain active. Because `DbQueryInterceptor` resolves to
`FakeQueryInterceptor`, SQL execution is still replaced by fixtures.

## Fixture Vocabulary

Fixture filenames are derived from the `#[DbQuery]` id:

| Query shape | Fixture | Result |
|-------------|---------|--------|
| row / nullable row | `<query_id>.json` | one object, raw row, or `null` |
| row list | `<query_id>.jsonl` | one JSON object per line |
| `void` command | no fixture | no-op success |

Nested query ids are represented by nested paths, for example
`#[DbQuery('admin/profile')]` maps to `admin/profile.json`.

Unknown `.json` or `.jsonl` files fail fast during module configuration. This
keeps fixture directories as a shared vocabulary rather than a loose set of
unused mock files.

## Hydration

Default entity hydration follows Ray.MediaQuery conventions:

- JSON keys may use `snake_case`
- constructor parameters and object properties use `camelCase`
- constructor defaults are respected
- raw `array` return types receive raw fixture rows

When `#[DbQuery(factory: SomeFactory::class)]` is used, FakeQuery calls the
configured Ray.MediaQuery factory method name, currently `factory` by default.
Static methods are invoked statically. Non-static methods are resolved through
the injector. Fixture fields are passed as positional arguments in JSON object
order, matching PDO `FETCH_FUNC` semantics.

Invalid factory classes, missing factory methods, or non-public factory methods
throw `InvalidFactoryException`; they never silently fall back to default entity
hydration.

## Non Goals

- No fake PDO layer.
- No mutable in-memory database.
- No inferred insert/update/delete side effects.
- No automatic DML metadata until the dedicated `AffectedRows` / `InsertedRow`
  fixture design is implemented.
