# Progress — Ray.FakeQuery Release Hardening

## Session: 2026-05-15

### Completed
- Reviewed Ray.FakeQuery public README, composer metadata, source, tests, and
  `.planning` files.
- Verified the current implementation uses `.json` for row and `.jsonl` for
  row-list fixtures.
- Verified `composer tests` passes locally on PHP 8.5.
- Verified `composer coverage` passes locally with 96.55% line coverage.
- Verified `composer crc` currently fails on undeclared direct dependencies.
- Created branch `codex/fake-query-release-hardening`.
- Replaced obsolete `.planning` implementation notes with a release-hardening
  plan driven by BEAR.AppKata / MyVendor.Cms use cases.
- Spawned sub-agents for:
  - Ray.MediaQuery 1.1 / BDR feature gap analysis.
  - composer / CI / release hygiene review.
  - BEAR.AppKata and MyVendor.Cms acceptance criteria extraction.
- Received sub-agent findings and incorporated the high-priority criteria:
  nested query ids, factory hydration, select-side result wrappers,
  pager support, and parameter-aware resolver as an extension point.
- Fixed the first release-hygiene blocker:
  - direct `ray/aop` and `phpdocumentor/reflection-docblock` dependencies,
  - `ray/media-query:^1.1` baseline,
  - package metadata,
  - GitHub Actions workflow,
  - `composer crc` passing.
- Added support and tests for:
  - static `#[DbQuery(factory: ...)]` hydration,
  - injected factory hydration,
  - factory row-list hydration,
  - nested query id fixture loading,
  - recursive unknown fixture validation,
  - constructor-based SELECT result wrappers.
- Removed the fake PDO direction from the current scope. DML metadata results
  can be added later as direct metadata fixture handling if needed.

### In Progress
- Implement Ray.MediaQuery 1.1 result support in priority order.

## Test Results

| Date | Command | Result | Notes |
|------|---------|--------|-------|
| 2026-05-15 | `composer tests` | pass | phpcs, phpstan, psalm, phpunit passed. |
| 2026-05-15 | `composer coverage` | pass | Line coverage 96.55%. |
| 2026-05-15 | `composer crc` | fail | Missing direct dependency declarations for phpdocumentor and ray/aop symbols. |
| 2026-05-15 | `composer validate --strict && composer crc && vendor/bin/phpunit` | pass | Composer metadata, direct dependency scan, and unit tests passed. |
| 2026-05-15 | `composer tests && composer crc && composer validate --strict` | pass | phpcs, phpstan, psalm, phpunit, CRC, and composer validation passed. |

## Files Modified
- `.planning/task_plan.md`
- `.planning/findings.md`
- `.planning/progress.md`
- `.github/workflows/ci.yml`
- `README.md`
- `composer.json`
- `composer.lock`
- `vendor-bin/require-checker/composer.lock`
- `vendor-bin/tools/composer.lock`
- `src/FakeQueryModule.php`
- `src/FakeQueryInterceptor.php`
- `src/JsonHydrator.php`
- `tests/FakeQueryModuleTest.php`
- `tests/Fake/Entity/FactoryTodoEntity.php`
- `tests/Fake/Factory/InjectedTodoFactory.php`
- `tests/Fake/Factory/StaticTodoFactory.php`
- `tests/Fake/Query/FactoryTodoQueryInterface.php`
- `tests/Fake/Query/TodoSelectionQueryInterface.php`
- `tests/Fake/Result/TodoSelection.php`
- `tests/Fake/factory_static_item.json`
- `tests/Fake/factory_injected_item.json`
- `tests/Fake/factory_static_list.jsonl`
- `tests/Fake/nested/factory_static_item.json`
- `tests/FakeUnknownNested/nested/stray_query.json`
