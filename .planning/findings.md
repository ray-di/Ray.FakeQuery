# Findings — Ray.FakeQuery Release Hardening

## Current Package State
- Repository: `/Users/akihito/git/Ray.FakeQuery`
- Branch at start: `1.x`
- Baseline commit: `e9bf11042a41c03d879319b2c94b7d4ccdb1a8db`
- No release tags were present in the remote tag listing.
- Packagist/composer metadata exposes `1.x-dev` and no stable tag.

## Current Implementation
- `FakeQueryModule` scans query interfaces with `Ray\MediaQuery\Queries::fromDir()`.
- Query interfaces are bound to Ray.Di null objects and intercepted on
  `#[DbQuery]` methods.
- `FakeQueryInterceptor` maps:
  - row return paths to `<query_id>.json`
  - row-list return paths to `<query_id>.jsonl`
  - `void` methods to no-op.
- `JsonHydrator` supports:
  - raw arrays when no entity type is resolved,
  - public-property entities,
  - constructor entities with camelCase / snake_case key matching.
- Current tests cover basic row, row-list, explicit `type: 'row'`, constructor
  hydration, void no-op, unknown fixture files, invalid fake dir, and nullable
  missing-file behavior.

## Baseline Commands
- `composer tests` passed on PHP 8.5.
- `composer coverage` passed with line coverage 96.55%.
- `composer crc` failed because currently used symbols from `phpdocumentor` and
  `ray/aop` are not declared as direct dependencies.

## Documentation Drift
- `.planning` was older than the implementation.
- The old plan expected row-list `.json`; implementation and README now use
  `.jsonl`.
- JSONL is the right direction for row-list fixtures because diffs are stable and
  each line is a canonical row example.
- The old plan expected missing nullable fixture files to throw, while current
  tests return `null` for nullable methods. For release compatibility and
  Ray.MediaQuery "no row" parity, the current default remains `null`; stricter
  fixture-completeness validation can be added separately.

## BEAR.AppKata / MyVendor.Cms Release Criteria
- Fake fixtures must be reusable as shared domain vocabulary, not just local
  mocks.
- BDR result support matters:
  - `Ray\MediaQuery\Result\AffectedRows`
  - `Ray\MediaQuery\Result\InsertedRow`
  - custom `PostQueryInterface` wrappers for SELECT row lists
  - typed selection result objects such as `ArticleSelection`
- Pager support matters for `PagesInterface` / `#[Pager]`.
- `#[DbQuery(factory: ...)]` matters because MyVendor.Cms uses factories for
  Article entity hydration.
- Nested query IDs matter because BEAR.AppKata uses ids such as
  `admins/admin_selection_list`; fake file validation must recurse and preserve
  query id paths.
- Parameter-aware resolver support is likely a post-1.0 extension unless the
  first stable release aims to replace MyVendor.Cms' stateful `FakeSqlQuery`.

## Open Technical Questions
- Exact fake fixture shape for `AffectedRows` and `InsertedRow`.
- Exact fake fixture shape for `PagesInterface`.
- Whether fake `PostQueryContext` needs a lightweight PDOStatement/PDO adapter
  double, or whether Ray.FakeQuery should construct known result classes directly
  and only use `fromContext()` for SELECT wrappers.
- Whether 1.0.0 must include a stateful resolver layer for write/read round trips
  or whether static fixtures plus BDR metadata fixtures are sufficient.
