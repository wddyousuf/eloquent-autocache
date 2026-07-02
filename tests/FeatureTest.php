<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\Event;
use Wddyousuf\AutoCache\Events\CacheFlushed;
use Wddyousuf\AutoCache\Events\CacheHit;
use Wddyousuf\AutoCache\Events\CacheMissed;
use Wddyousuf\AutoCache\Tests\Models\Post;

class FeatureTest extends TestCase
{
    public function test_without_cache_always_queries_the_database(): void
    {
        $selects = $this->countSelects(function () {
            Post::withoutCache()->get();
            Post::withoutCache()->get();
        });

        $this->assertSame(2, $selects);
    }

    public function test_cache_for_overrides_ttl_but_still_caches(): void
    {
        $selects = $this->countSelects(function () {
            Post::cacheFor(60)->get();
            Post::cacheFor(60)->get();
        });

        $this->assertSame(1, $selects);
    }

    public function test_disabled_globally_bypasses_cache(): void
    {
        config()->set('autocache.enabled', false);

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects);
    }

    public function test_hit_and_miss_events_are_dispatched(): void
    {
        Event::fake([CacheHit::class, CacheMissed::class]);

        Post::all(); // miss
        Post::all(); // hit

        Event::assertDispatched(CacheMissed::class);
        Event::assertDispatched(CacheHit::class);
    }

    public function test_flushed_event_is_dispatched_on_write(): void
    {
        Event::fake([CacheFlushed::class]);

        Post::create(['title' => 'evented']);

        Event::assertDispatched(CacheFlushed::class);
    }

    public function test_result_larger_than_max_rows_is_not_cached(): void
    {
        config()->set('autocache.max_rows', 1); // we have 2 posts

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects, 'Oversized result should not be cached.');
    }

    public function test_volatile_query_is_not_cached(): void
    {
        config()->set('autocache.volatile_patterns', ['"posts"']); // force a match

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects);
    }

    public function test_caching_works_with_stampede_lock_enabled(): void
    {
        config()->set('autocache.lock_for', 10); // array store is lock-capable

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(1, $selects);
    }

    public function test_stats_are_collected_when_enabled(): void
    {
        config()->set('autocache.stats', true);

        Post::all(); // miss
        Post::all(); // hit
        Post::all(); // hit

        $stats = app('autocache')->stats();
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(2, $stats['hits']);
    }
}
