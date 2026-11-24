# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 - 2026-11-25

### Added
- `FakeQueryModule` answers the `#[DbQuery]` methods of the interfaces in a directory from JSON fixtures instead of SQL: `{id}.json` for a row (`null` for a nullable row, or no file), `{id}.jsonl` for a row list with one object per line; `void` methods are no-ops
- Entities are hydrated by name: `snake_case` or `camelCase` keys map to properties or constructor parameters, and `#[DbQuery(factory: ...)]` factories receive the fixture fields in object order
- Broken or mismatched fixtures throw: `InvalidFakeDirException`, `FakeJsonNotFoundException`, `UnknownFakeJsonException`, `InvalidFakeJsonException` (naming the file and JSONL line), `MissingFakeFieldException`, `InvalidFactoryException`
- `#[Pager]` methods and `PagesInterface` / `PostQueryInterface` return types throw `UnsupportedQueryException`
- When installed together with `MediaQuerySqlModule`, `FakeQueryModule` takes over `#[DbQuery]` methods in any install order, including `override()`
- Requires PHP ^8.2
