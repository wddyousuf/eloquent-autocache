# AutoCache

Automatic, self-invalidating query caching for Eloquent models.

Add one trait to a model and its read queries are cached transparently. Any
write — including bulk, raw, and event-suppressing "quiet" writes — flushes
that model's cache automatically, so you never serve stale data.

Invalidation uses **cache tags** when the store supports them (immediate,
targeted flushing) and falls back to a **per-model version counter** on every
other store (`file`, `database`, `array`, …). Either way, it just works.

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [What triggers a flush](#what-triggers-a-flush)
- [Per-query controls](#per-query-controls)
- [Caching modes (auto vs opt-in)](#caching-modes-auto-vs-opt-in)
- [Row-level caching](#row-level-caching)
- [Relationship-aware invalidation](#relationship-aware-invalidation)
- [Stale-while-revalidate](#stale-while-revalidate)
- [Facade & Artisan commands](#facade--artisan-commands)
- [Events](#events)
- [Testing your application](#testing-your-application)
- [Configuration](#configuration)
- [Per-model overrides](#per-model-overrides)
- [Laravel Octane](#laravel-octane)
- [Limitations & notes](#limitations--notes)
- [Comparison with other packages](#comparison-with-other-packages)
- [Contributing](#contributing)

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

Install via Composer:

```bash
composer require wddyousuf/eloquent-autocache
```

The service provider and `AutoCache` facade are auto-discovered — no manual
registration needed.

Optionally publish the config file to `config/autocache.php`:

```bash
php artisan vendor:publish --tag=autocache-config
```

That's the whole setup. Every store works out of the box; there's nothing to
migrate and no external service to run.

## Quick start

Add the `Cacheable` trait to any Eloquent model:

```php
use Wddyousuf\AutoCache\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Cacheable;
}
```

Now reads are cached and writes flush automatically:

```php
Post::where('published', true)->get();   // hits the DB, caches the result
Post::where('published', true)->get();   // served from cache
Post::count();                           // cached
Post::find(1);                           // cached (per-row, see below)

Post::create(['title' => 'Hello']);      // flushes Post's cache

Post::where('published', true)->get();   // fresh from the DB again
```

## How it works

1. The `Cacheable` trait backs the model with a `CachedQueryBuilder`.
2. Every SELECT funnels through the builder's `runSelect()` (and `exists()`),
   so `get`, `first`, `find`, `pluck`, `value`, `count`, `sum`, `exists`, and
   even the pagination count query are all cached from one place.
3. Every write funnels through the builder's write methods, which flush the
   model's cache — catching bulk updates, raw inserts, `increment`, `truncate`,
   and quiet writes that bypass model events.
4. Flushing either clears the model's cache tags or bumps a per-model version
   counter, depending on whether the store supports tags.

## What triggers a flush

- `create` / `save` / `update` / `delete` / `restore` / `forceDelete`
- Bulk `Post::where(...)->update()` / `->delete()` (including joined updates)
- Raw `insert` / `upsert` / `insertOrIgnore` / `insertUsing` / `insertOrIgnoreUsing`
- `updateFrom`
- `increment` / `decrement`
- `truncate`
- The `*Quietly()` variants (`saveQuietly`, `updateQuietly`, …)

## Per-query controls

```php
Post::withoutCache()->get();              // skip the cache for this query
Post::cacheFor(60)->get();                // custom TTL (seconds; null = forever) for this query
Post::cache()->where('active', 1)->get(); // explicitly opt in (opt-in mode)
Post::query()->cacheKey('homepage')->get(); // use a fixed cache key
```

Each also works from an existing builder chain, e.g.
`Post::where(...)->withoutCache()->get()`.

## Caching modes (auto vs opt-in)

By default (`mode => 'auto'`) every read is cached. Set the mode to `opt-in`
to cache **only** queries you explicitly mark:

```php
// config/autocache.php
'mode' => 'opt-in',
```

```php
Post::all();                 // NOT cached
Post::cache()->get();        // cached
Post::cacheFor(120)->get();  // cached (cacheFor implies opt-in)
```

The mode can also be set per model, overriding the global setting:

```php
class AuditLog extends Model
{
    use Cacheable;

    protected $cacheMode = 'opt-in'; // or 'auto'
}
```

Writes always flush, regardless of mode.

## Row-level caching

Canonical `find($id)` lookups are cached under a stable per-row key, so a
single row's cache **survives writes to other rows**:

```php
Post::find(1);   // cached
Post::find(2);   // cached

Post::find(2)->update(['title' => 'Changed']); // only row 2's cache is dropped

Post::find(1);   // still served from cache
Post::find(2);   // refetched (fresh)
```

Bulk updates, `truncate`, and `upsert` clear all row caches (they may touch
unknown rows). Missing rows are never cached, so a later insert is picked up
immediately. Non-canonical finds (extra constraints, custom columns, removed
scopes, eager loads) fall back to normal query caching.

Disable it with `'row_cache' => false` (or per model, see below). Row-level
survival applies to version-counter stores; tag stores flush per-model.

## Relationship-aware invalidation

Flush a parent (or any related model) whenever this model changes:

```php
class Comment extends Model
{
    use Cacheable;

    protected $flushRelated = ['post']; // relation name or model class

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

Creating or updating a `Comment` now also flushes the cached `Post` queries.

## Stale-while-revalidate

On Laravel 11+, serve an expired value instantly while it recomputes in the
background (via `Cache::flexible()`):

```php
// config/autocache.php
'ttl' => 60,   // fresh for 60s
'swr' => 30,   // then served stale for up to 30s more while refreshing
```

SWR needs a finite TTL, and it is skipped for models with a `max_rows` cap
(the background refresh cannot apply the size guard).

## Facade & Artisan commands

```php
use Wddyousuf\AutoCache\Facades\AutoCache;

AutoCache::flush(Post::class);   // flush one model
AutoCache::clear();              // flush all registered models
AutoCache::warm(Post::class);    // pre-populate a model's cache
AutoCache::warmAll();            // warm every registered model
AutoCache::stats();              // ['hits' => ..., 'misses' => ...]
AutoCache::resetStats();         // zero the counters (optionally per model)
```

```bash
php artisan autocache:flush "App\Models\Post"
php artisan autocache:clear
php artisan autocache:warm "App\Models\Post"
php artisan autocache:warm --all
php artisan autocache:stats
php artisan autocache:stats --reset
```

Customize what warming runs by overriding `cacheWarmupQueries()` on the model:

```php
public function cacheWarmupQueries(): array
{
    return [
        static::query(),
        static::where('published', true),
    ];
}
```

`clear` and `warm` discover models registered at runtime; list any that must
be reachable before boot in `config('autocache.models')`.

## Events

Three events are dispatched so you can log or measure cache behavior:

- `Wddyousuf\AutoCache\Events\CacheHit`
- `Wddyousuf\AutoCache\Events\CacheMissed`
- `Wddyousuf\AutoCache\Events\CacheFlushed`

Each carries the `$model` (and, for hit/miss, the `$key`).

## Testing your application

Swap in a recording fake and assert on cache behavior — no counting SQL by
hand:

```php
use Wddyousuf\AutoCache\Facades\AutoCache;

public function test_publishing_flushes_the_cache(): void
{
    $fake = AutoCache::fake();

    Post::factory()->create();

    $fake->assertFlushed(Post::class);
}
```

Available assertions:

```php
$fake->assertFlushed(Post::class);
$fake->assertNotFlushed(Post::class);
$fake->assertNothingFlushed();
$fake->assertHit(Post::class);    // or assertHit() for "any"
$fake->assertMissed(Post::class); // or assertMissed() for "any"
```

## Configuration

`config/autocache.php` (every key is env-driven):

| Key                 | Default          | Description                                                        |
|---------------------|------------------|--------------------------------------------------------------------|
| `enabled`           | `true`           | Master on/off switch.                                              |
| `store`             | `null`           | Cache store (`null` = app default).                               |
| `ttl`               | `3600`           | Seconds to cache; `null` = forever.                               |
| `ttl_jitter`        | `0.1`            | Randomly spread each TTL by ±this fraction (anti thundering-herd). |
| `prefix`            | `autocache`      | Key prefix.                                                        |
| `mode`              | `auto`           | `auto` caches everything; `opt-in` caches only `->cache()` queries. |
| `row_cache`         | `true`           | Per-row caching for canonical `find($id)`.                        |
| `swr`               | `0`              | Stale-while-revalidate grace seconds (Laravel 11+; 0 = off).      |
| `use_tags`          | `auto`           | `auto`/`true` = tags when supported; `false` = version counter.   |
| `lock_for`          | `10`             | Seconds to hold a stampede lock (needs a lock-capable store; 0 off). |
| `max_rows`          | `null`           | Skip caching result sets larger than this.                        |
| `volatile_patterns` | `now(), rand(, …`| Queries containing these substrings are never cached.             |
| `stats`             | `false`          | Collect hit/miss counters.                                        |
| `models`            | `[]`             | Models for `clear`/`warm` to discover before boot.                |

Env variables: `AUTOCACHE_ENABLED`, `AUTOCACHE_STORE`, `AUTOCACHE_TTL`,
`AUTOCACHE_TTL_JITTER`, `AUTOCACHE_PREFIX`, `AUTOCACHE_MODE`,
`AUTOCACHE_ROW_CACHE`, `AUTOCACHE_SWR`, `AUTOCACHE_USE_TAGS`,
`AUTOCACHE_LOCK_FOR`, `AUTOCACHE_MAX_ROWS`, `AUTOCACHE_STATS`.

## Per-model overrides

Declare any of these properties to override the global config for one model:

```php
class Post extends Model
{
    use Cacheable;

    protected $cacheStore   = 'redis';     // this model's store
    protected $cacheTtl     = 600;         // seconds; null = forever
    protected $cacheEnabled = true;        // disable caching for just this model
    protected $cacheMode    = 'opt-in';    // 'auto' or 'opt-in' for this model
    protected $cacheTags    = ['catalog']; // extra tags (tag mode)
    protected $cacheMaxRows = 5000;        // skip caching larger results
    protected $flushRelated = ['comments'];// relations/models to co-flush
}
```

## Laravel Octane

AutoCache is Octane-safe: it registers listeners on `RequestReceived`,
`TaskReceived`, and `TickReceived` to reset its process-static flush guard
between requests, so a long-lived worker never carries state across requests.
Nothing to configure.

## Limitations & notes

- **Eager-loaded relations** are cached as part of the related model's own
  queries (which must also use `Cacheable`). A change to a related model does
  not flush a parent's *root* query unless you wire it up with `$flushRelated`.
- **`cursor()`** streams and is intentionally never cached.
- **Direct `DB::table()` writes** bypass Eloquent entirely; call
  `AutoCache::flush(Model::class)` afterward if you use them.
- The **version counter** on non-atomic stores (`file`) can, under heavy
  concurrent writes, briefly miss an increment. Use an atomic store (redis,
  memcached) or a taggable store for high-write workloads.
- The **`array` store** keeps values by reference, so repeated `find($id)`
  calls return the *same* model instance — unsaved attribute changes on it
  will be visible to later calls. Serializing stores (redis, file, database,
  memcached) are unaffected.

## Comparison with other packages

Several excellent packages cache Eloquent queries. AutoCache's focus is
**complete write-path coverage on any cache store** — no Redis requirement,
and no write that can slip past invalidation.

|                                                        | AutoCache | [laravel-model-caching](https://github.com/mike-bronner/laravel-model-caching) | [eloquent-query-cache](https://github.com/renoki-co/laravel-eloquent-query-cache) | [lada-cache](https://github.com/spiritix/lada-cache) |
|--------------------------------------------------------|:---------:|:----------------------:|:--------------------:|:----------:|
| Works on **any** cache store (`file`, `database`, …)   | ✅        | ❌ ¹                   | ⚠️ ²                 | ❌ (Redis only) |
| Automatic caching (zero per-query code)                | ✅        | ✅                     | ⚠️ ³                 | ✅         |
| Opt-in / per-query mode                                | ✅        | ❌                     | ✅                   | ❌         |
| Bulk `where(...)->update()` / `->delete()` flushes     | ✅        | ❌ ⁴                   | ❌ ⁴                 | ✅         |
| Raw `insert` / `upsert` / `insertUsing` flushes        | ✅        | ❌ ⁴                   | ❌ ⁴                 | ✅         |
| Quiet writes (`saveQuietly`, …) flush                  | ✅        | ❌ ⁴                   | ❌ ⁴                 | ✅         |
| Transaction-aware invalidation                         | ✅        | ❌ (manual flush)      | ❌                   | ❌         |
| Row-level `find()` cache survives other rows' writes   | ✅        | ❌                     | ❌                   | ✅         |
| Stale-while-revalidate                                 | ✅        | ❌                     | ❌                   | ❌         |
| Stampede (dog-pile) protection                         | ✅        | ❌                     | ❌                   | ❌         |
| TTL jitter (anti thundering-herd)                      | ✅        | ❌                     | ❌                   | ❌         |
| Test fake with assertions (`AutoCache::fake()`)        | ✅        | ❌                     | ❌                   | ❌         |
| Warm / clear / stats Artisan commands                  | ✅        | ⚠️ (flush)             | ❌                   | ⚠️ (flush) |

¹ Requires Redis, Memcached, APC, or DynamoDB; `file`, `database`, and `array`
  stores are unsupported.
² Per-query caching works anywhere, but *automatic* invalidation requires a
  taggable store.
³ Caches only queries marked `cacheFor()` (or a model-wide `$cacheFor`
  property).
⁴ Invalidation hooks Eloquent model events, so writes that bypass events
  (bulk builder writes, raw inserts, quiet saves) leave stale cache entries.
  AutoCache and lada-cache hook the query-builder layer instead.

[rememberable](https://github.com/dwightwatson/rememberable) is also worth a
mention: a minimal manual `remember($seconds)` per query, with no automatic
invalidation. And
[mostafaznv/laracache](https://github.com/mostafaznv/laracache) is a
different approach entirely — you predefine named `CacheEntity` queries on the
model rather than caching reads transparently.

*Based on each package's documentation as of July 2026 — corrections welcome.*

## Contributing

```bash
composer install
composer test        # phpunit
composer analyse     # phpstan / larastan
composer format      # pint
```

## License

MIT
