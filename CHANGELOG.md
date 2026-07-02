# Changelog

All notable changes to `laracache` will be documented in this file.

## Unreleased

### Fixed
- Custom `->cacheKey()` results are now invalidated on writes when using
  version-counter stores (previously they were only ever evicted by TTL).
- `->cacheKey()` is now honored by `exists()` as well as `runSelect()`.
- Joined updates (e.g. `Post::join(...)->where('comments.id', 1)->update(...)`)
  no longer mistake the joined table's key for the model's primary key, which
  could leave a stale row cache for the row actually updated. Writes with
  joins now always trigger a full flush, and qualified key columns must belong
  to the model's own table to qualify for a surgical single-row flush.
- `insertUsing()`, `insertOrIgnoreUsing()`, and `updateFrom()` now flush the
  cache like every other write path.
- `cacheFor(null)` now caches forever as documented, instead of silently
  falling back to the default TTL.
- Row-level `find()` caching now honors a `cacheFor()` TTL override.

## 0.1.0 - 2026-07-02

Initial release.

### Added
- `Cacheable` trait: transparent, self-invalidating query caching for Eloquent
  models via a single trait.
- Row-level caching: canonical `find($id)` lookups are cached under a stable
  per-row key and survive writes to other rows (version-counter stores).
- Opt-in caching mode (`mode => 'opt-in'`) with `->cache()` / `Model::cache()`.
- Stale-while-revalidate (`swr`) via `Cache::flexible()` on Laravel 11+.
- `LaraCache::fake()` test double with `assertFlushed`, `assertNotFlushed`,
  `assertNothingFlushed`, `assertHit`, and `assertMissed`.
- Laravel Octane safety: process-static flush state resets each request/task/tick.
- Read caching at the base query builder (`runSelect`), so `get`,
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
