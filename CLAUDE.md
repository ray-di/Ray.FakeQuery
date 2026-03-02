# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`Ray.FakeQuery` is a companion package to [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery). It replaces SQL execution with JSON fixture files — no database required. Designed for testing and frontend development.

Reference implementations:
- Ray.MediaQuery source: `/Users/akihito/git/Ray.MediaQuery`
- BEAR.FakeJson (same concept for BEAR.Sunday resources): `https://github.com/bearsunday/BEAR.FakeJson`

## Commands

```bash
composer test                                              # Run PHPUnit tests
composer tests                                             # cs + phpstan + psalm + test (run before push)
composer cs-fix                                            # Fix coding style (phpcbf src tests)
composer sa                                                # PHPStan (level max) + Psalm
./vendor/bin/phpunit --filter TestMethodName               # Run a single test
./vendor/bin/phpunit --no-coverage                         # Run tests without coverage (fast)
composer coverage                                          # Generate HTML coverage to build/coverage/
```

## Architecture

### Current State

Skeleton only — the package structure is defined in `DESIGN.md`. Implement per that design doc.

```
src/
├── FakeQuery.php                # Placeholder — replace with actual classes
└── Exception/
    ├── LogicException.php       # Base logic exception (extend for domain exceptions)
    └── RuntimeException.php     # Base runtime exception (extend for domain exceptions)
```

### Target Architecture (from DESIGN.md)

```
src/
├── FakeQueryModule.php          # Ray.Di module — scans interfaceDir, binds #[DbQuery] interfaces
├── FakeQueryConfig.php          # Value object: readonly string $fakeDir
├── Interceptor/
│   └── FakeQueryInterceptor.php # Intercepts #[DbQuery] calls, loads JSON, hydrates
└── Hydrator/
    └── FakeJsonHydrator.php     # JSON → Entity hydration (snake_case → camelCase)
```

### Key Behaviors

1. `FakeQueryModule` scans `interfaceDir` for interfaces with `#[DbQuery]` — mirrors `MediaQuerySqlModule`
2. `FakeQueryInterceptor`: `#[DbQuery('todo_item')]` → `{fakeDir}/todo_item.json`
3. `void` return type → no-op (commands succeed silently, no JSON file needed)
4. Missing JSON file → throw `FakeJsonNotFoundException` with queryId + fakeDir in message
5. Hydration: `?Entity` → single object or null | `array<Entity>` (PHPDoc) → array | `array` → raw array
6. snake_case JSON keys → camelCase properties (same conversion as Ray.MediaQuery)

### Exception Pattern

Add specific exceptions to `src/Exception/` extending the base classes already there:

```php
// src/Exception/FakeJsonNotFoundException.php
final class FakeJsonNotFoundException extends RuntimeException { ... }
```

### DI Annotation

- `DbQuery` attribute lives in `Ray\MediaQuery\Annotation\DbQuery`
- Binding: `$this->bindInterceptor(...)` in Ray.Di style

## Namespace & Autoload

- Production: `Ray\FakeQuery\` → `src/`
- Test: `Ray\FakeQuery\` → `tests/` and `tests/Fake/` (both mapped to same namespace)

## Static Analysis

- PHPStan: level max (`phpstan.neon`)
- Psalm: `psalm.xml`
- Both run via `composer sa`
