# Ray.FakeQuery Design Notes

Ray.FakeQuery is a fixture adapter for Ray.MediaQuery query interfaces. It keeps
the application-facing query interface unchanged while replacing SQL execution
with JSON or JSONL fixture data.

## Scope

The current scope is intentionally limited to select-style fake responses:

- row and nullable row responses from `<query_id>.json`
- row list responses from `<query_id>.jsonl`
- `void` command methods as no-op methods
- `PostQueryInterface` select result wrappers, built through `fromContext()`
  with hydrated fixture rows; the context's statement and connection are an
  idle in-memory SQLite placeholder
- static and injected factory hydration via `#[DbQuery(factory: ...)]`

Pagination (`#[Pager]` / `PagesInterface`) and DML metadata result objects such
as `AffectedRows` and `InsertedRow` throw `UnsupportedQueryException` until they
get a fixture design. They should remain fixture-driven and must not turn
FakeQuery into a fake PDO or mutable in-memory database.

## Module Replacement

`FakeQueryModule` binds all Ray.MediaQuery query interfaces from the configured
interface directory to null-object proxies, the same proxy surface used by
Ray.MediaQuery.

Ray.Aop keeps only the last merged pointcut per annotation, so FakeQuery cannot
rely on its own `#[DbQuery]` pointcut winning. It binds its interceptor and also
takes over the MediaQuery interceptor token:

```php
$this->bindInterceptor(
    $this->matcher->any(),
    $this->matcher->annotatedWith(DbQuery::class),
    [FakeQueryInterceptor::class],
);
$this->bind(DbQueryInterceptor::class)->to(FakeQueryInterceptor::class);
```

When FakeQuery's pointcut wins, `FakeQueryInterceptor` runs directly. When
MediaQuery's pointcut wins, its `DbQueryInterceptor` resolves to
`FakeQueryInterceptor`. Either way fixtures replace SQL execution, whether the
modules are installed in either order, nested, or combined with `override()`
(see `ModuleCompositionTest`).

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
- constructor defaults are respected; a missing nullable parameter gets `null`,
  and any other missing parameter throws `MissingFakeFieldException`
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
