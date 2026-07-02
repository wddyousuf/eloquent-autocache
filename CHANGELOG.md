# Changelog

All notable changes to `wddyousuf/eloquent-autocache` will be documented in
this file.

## 0.1.0 - 2026-07-02

Initial release.

### Added
- `Cacheable` trait: transparent, self-invalidating query caching for Eloquent
  models via a single trait.
- Row-level caching: canonical `find($id)` lookups are cached under a stable
  per-row key and survive writes to other rows (version-counter stores).
- Opt-in caching mode (`mode => 'opt-in'`) with `->cache()` / `Model::cache()`,
  plus a per-model `$cacheMode` property overriding the global mode.
- Stale-while-revalidate (`swr`) via `Cache::flexible()` on Laravel 11+
  (skipped for models with a `max_rows` cap so the size guard always applies).
- `AutoCache::fake()` test double with `assertFlushed`, `assertNotFlushed`,
  `assertNothingFlushed`, `assertHit`, and `assertMissed`.
- Laravel Octane safety: process-static flush state resets each request/task/tick.
- Read caching at the base query builder (`runSelect`), so `get`,
  `first`, `find`, `pluck`, `value`, aggregates (`count`/`sum`/`avg`/`min`/`max`),
  `exists`, and pagination counts are all cached uniformly.
- Automatic invalidation on **every** write path, including bulk and joined
  query-builder writes, raw `insert`/`upsert`/`insertOrIgnore`/`insertUsing`/
  `insertOrIgnoreUsing`/`updateFrom`, `increment`/`decrement`, `truncate`, and
  event-suppressing "quiet" writes.
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
- `AutoCache` facade and `autocache:flush`, `autocache:clear`,
  `autocache:warm` (with `--all`), and `autocache:stats` (with `--reset`)
  Artisan commands.
