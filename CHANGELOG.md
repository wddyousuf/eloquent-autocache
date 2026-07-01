# Changelog

All notable changes to `laracache` will be documented in this file.

## Unreleased

### Added (round 2)
- Row-level caching: canonical `find($id)` lookups are cached under a stable
  per-row key and survive writes to other rows (version-counter stores).
- Opt-in caching mode (`mode => 'opt-in'`) with `->cache()` / `Model::cache()`.
- Stale-while-revalidate (`swr`) via `Cache::flexible()` on Laravel 11+.
- `LaraCache::fake()` test double with `assertFlushed`, `assertNotFlushed`,
  `assertNothingFlushed`, `assertHit`, and `assertMissed`.
- Laravel Octane safety: process-static flush state resets each request/task/tick.

### Added
- Read caching moved to the base query builder (`runSelect`), so `get`,
  `first`, `find`, `pluck`, `value`, aggregates (`count`/`sum`/`avg`/`min`/`max`),
  `exists`, and pagination counts are all cached uniformly.
- Automatic invalidation on **every** write path, including bulk query-builder
  writes, raw `insert`/`upsert`/`insertOrIgnore`, `increment`/`decrement`,
  `truncate`, and event-suppressing "quiet" writes.
- Tag-based invalidation, auto-detected on taggable stores, with a version-
  counter fallback for every other store.
- Relationship-aware invalidation via a `$flushRelated` property.
- Transaction-aware flushing: immediate flush for read-after-write consistency
  plus an after-commit re-flush; rollbacks leave the cache untouched.
- Stampede protection using cache locks (`lock_for`).
- TTL jitter (`ttl_jitter`) to avoid synchronized expiry.
- Result-size guard (`max_rows`) and volatile-query skipping.
- Per-query controls: `withoutCache()`, `cacheFor()`, `cacheKey()`, plus the
  static `Model::withoutCache()` / `Model::cacheFor()` entry points.
- `CacheHit`, `CacheMissed`, and `CacheFlushed` events.
- Optional hit/miss statistics (`stats`).
- `LaraCache` facade and `laracache:flush`, `laracache:clear`,
  `laracache:warm`, and `laracache:stats` Artisan commands.

## 0.1.0

- Initial release: `Cacheable` trait with version-based invalidation.
