# Task Plan — Ray.FakeQuery Release Hardening

## Goal
Use the BEAR.AppKata / MyVendor.Cms modernization use cases as the release
engine for Ray.FakeQuery, then prepare a stable package release.

Ray.FakeQuery should let tests and frontend development replace
Ray.MediaQuery SQL execution with executable fixture vocabulary:

- `#[DbQuery]` interface remains the public contract.
- row fixtures use `.json`.
- row-list fixtures use `.jsonl`, one object per line.
- fixture names follow the Ray.MediaQuery query id.
- fake data hydrates through the same Entity / result contracts the application uses.

## Release Judgment
- `1.x-dev` is useful today for experiments.
- A narrow `0.1.0` tag would be acceptable for current row / row-list support.
- The target for this work is **1.0.0 readiness**, because BEAR.AppKata needs
  Ray.MediaQuery 1.1 result semantics, not only simple entity hydration.

## Source Use Cases
- `/Users/akihito/git/bear-app`
  - Admin read fixtures as shared domain vocabulary.
  - BDR samples: typed selection result wrappers and smoke tests.
- `/Users/akihito/git/MyVendor.Cms`
  - Existing `FakeSqlQuery` behavior.
  - `ArticleSelection`, `PagesInterface`, and current DML fake behavior.
  - MediaQuery smoke tests and fake pager examples.

## Decisions
- JSONL is the canonical row-list fixture format. Older `.planning` references to
  row-list `.json` are obsolete.
- Nullable row methods keep Ray.MediaQuery "no row" parity: a missing row fixture
  returns `null`. Non-nullable rows and row lists still throw when the fixture is
  absent. To make a no-row example explicit, a `.json` file containing `null`
  remains valid; empty `.jsonl` files represent empty lists.
- Fake classes remain useful only for behavior outside Ray.FakeQuery scope.
  Direct app-local fake query classes should shrink as Ray.FakeQuery covers the
  shared query/result contract.
- Keep 1.0.0 scope select-side first. DML metadata results such as
  `AffectedRows` and `InsertedRow` should not require fake PDO machinery; if
  they are added, they should be built directly from explicit metadata fixtures.

## Status Legend
- [ ] pending
- [~] in progress
- [x] complete
- [!] blocked / needs decision

## Phase 0: Baseline Assessment [x]
- [x] Confirm current branch and HEAD.
- [x] Run current `composer tests`.
- [x] Run coverage check.
- [x] Run `composer crc` and record blocker.
- [x] Inspect current README and `.planning` drift.

## Phase 1: Planning Synchronization [x]
- [x] Replace obsolete implementation plan with release-hardening plan.
- [x] Record JSONL as the canonical list fixture format.
- [x] Record BEAR.AppKata / MyVendor.Cms as release acceptance sources.
- [x] Incorporate sub-agent findings into this plan.

## Phase 2: Release Hygiene [~]
- [x] Fix direct dependency declarations so `composer crc` passes.
- [x] Add package description, keywords, support metadata, and CI badges only if
      they reflect real checks.
- [x] Add GitHub Actions for PHP 8.2, 8.3, 8.4, and 8.5 where available.
- [ ] Run highest and lowest dependency test jobs, or document why lowest is not
      currently practical.
- [x] Update README release scope and semantics.

## Phase 3: Fixture Contract And Diagnostics [ ]
- [x] Support nested query ids by loading and validating subdirectory fixtures.
- [ ] Keep nullable missing row fixtures returning `null`; add an optional strict
      validation path only if callers need every query id to be materialized.
- [ ] Make missing non-nullable row and row-list fixture files throw a clear exception.
- [ ] Keep void `#[DbQuery]` methods as no-op without a fixture file.
- [ ] Support explicit `null` in row `.json` fixtures for `?Entity` misses.
- [ ] Keep empty `.jsonl` valid for empty row-list results.
- [ ] Add invalid JSON / invalid JSONL diagnostics.
- [ ] Replace `assert()`-dependent runtime validation with explicit exceptions
      for fixture shape and hydration errors.

## Phase 4: Ray.MediaQuery 1.1 Select Result Support [~]
- [x] Add `#[DbQuery(factory: ...)]` hydration parity for static and injected
      factory classes.
- [x] Add support for constructor-based custom `PostQueryInterface` wrappers
      over hydrated SELECT rows, such as MyVendor.Cms `ArticleSelection`.
- [ ] Preserve `#[DbQuery(factory: ...)]` semantics for row and row-list
      hydration where possible.
- [ ] Add tests that mirror BEAR.AppKata / MyVendor.Cms BDR samples.
- [ ] Keep DML metadata (`AffectedRows`, `InsertedRow`) as a separate optional
      phase; do not introduce fake PDO just to satisfy `PostQueryContext`.

## Phase 5: Pager Support [ ]
- [ ] Add fake `PagesInterface` support for methods annotated with `#[Pager]`.
- [ ] Decide fixture shape for paged row lists without making DB dumps.
- [ ] Add tests for count, page access, per-page argument, and empty pages.
- [ ] Compare behavior against MyVendor.Cms `FakePages`.

## Phase 6: Reference Integration Checks [ ]
- [ ] Try Ray.FakeQuery in a BEAR.AppKata hermetic test context for Admin read
      fixtures.
- [ ] Compare with MyVendor.Cms fake query smoke expectations.
- [ ] Record any missing upstream behavior as Ray.FakeQuery issues or local
      narrow adapters.
- [ ] Keep app fixtures as domain vocabulary, not raw mock assertions.

## Phase 7: Release Preparation [ ]
- [ ] Ensure `composer tests`, `composer coverage`, and `composer crc` pass.
- [ ] Ensure GitHub Actions are green.
- [ ] Update CHANGELOG or release notes.
- [ ] Decide tag:
      - `0.1.0` if only simple row / row-list support is guaranteed.
      - `1.0.0` if Phases 2-6 pass against BEAR.AppKata / MyVendor.Cms use cases.
- [ ] Create release PR.

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| `composer crc` reports `phpDocumentor\Reflection\DocBlockFactory*` and `Ray\Aop\Method*` as unknown symbols | Baseline release check | Add direct dependencies or adjust code so the package declares what it uses. |
| Existing fake file validation only scanned top-level fixtures | BEAR.AppKata query ids use `admins/...` paths | Replaced top-level glob with recursive validation preserving `/` query ids. |
