<?php

namespace Hcs\LaraCache;

use Hcs\LaraCache\Events\CacheFlushed;
use Hcs\LaraCache\Events\CacheHit;
use Hcs\LaraCache\Events\CacheMissed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Recording test double installed by {@see CacheManager::fake()}. Captures
 * cache hits, misses and flushes via events and exposes assertions.
 */
class LaraCacheFake extends CacheManager
{
    /** @var array<int, class-string<Model>> */
    public array $hits = [];

    /** @var array<int, class-string<Model>> */
    public array $misses = [];

    /** @var array<int, class-string<Model>> */
    public array $flushed = [];

    public function listen(): void
    {
        Event::listen(CacheHit::class, fn (CacheHit $e) => $this->hits[] = $e->model::class);
        Event::listen(CacheMissed::class, fn (CacheMissed $e) => $this->misses[] = $e->model::class);
        Event::listen(CacheFlushed::class, fn (CacheFlushed $e) => $this->flushed[] = $e->model::class);
    }

    public function assertFlushed(string $model): void
    {
        PHPUnit::assertContains($model, $this->flushed, "The cache for [{$model}] was not flushed.");
    }

    public function assertNotFlushed(string $model): void
    {
        PHPUnit::assertNotContains($model, $this->flushed, "The cache for [{$model}] was unexpectedly flushed.");
    }

    public function assertNothingFlushed(): void
    {
        PHPUnit::assertEmpty($this->flushed, 'Expected no caches to be flushed.');
    }

    public function assertHit(?string $model = null): void
    {
        $model === null
            ? PHPUnit::assertNotEmpty($this->hits, 'Expected at least one cache hit.')
            : PHPUnit::assertContains($model, $this->hits, "Expected a cache hit for [{$model}].");
    }

    public function assertMissed(?string $model = null): void
    {
        $model === null
            ? PHPUnit::assertNotEmpty($this->misses, 'Expected at least one cache miss.')
            : PHPUnit::assertContains($model, $this->misses, "Expected a cache miss for [{$model}].");
    }
}
