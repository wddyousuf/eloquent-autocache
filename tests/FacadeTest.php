<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\DB;
use Wddyousuf\AutoCache\Facades\AutoCache;
use Wddyousuf\AutoCache\Tests\Models\Post;

class FacadeTest extends TestCase
{
    public function test_facade_flush_invalidates_a_model(): void
    {
        $this->assertCount(2, Post::all()); // warm

        // Insert straight through the connection so AutoCache never sees the
        // write; the cache is now stale until we flush it manually.
        DB::table('posts')->insert(['title' => 'sneaky', 'published' => true, 'views' => 0]);
        $this->assertCount(2, Post::all(), 'Cache should still be stale here.');

        AutoCache::flush(Post::class);

        $this->assertCount(3, Post::all());
    }

    public function test_facade_clear_flushes_registered_models(): void
    {
        Post::all(); // boots + registers the model

        $this->assertContains(Post::class, AutoCache::registeredModels());
        $this->assertGreaterThanOrEqual(1, AutoCache::clear());
    }

    public function test_warm_command_populates_the_cache(): void
    {
        config()->set('autocache.models', [Post::class]);

        $this->artisan('autocache:warm', ['model' => Post::class])
            ->assertSuccessful();

        // Cache is now warm: a subsequent read performs no SELECT.
        $selects = $this->countSelects(fn () => Post::all());
        $this->assertSame(0, $selects);
    }

    public function test_warm_all_command_populates_registered_models(): void
    {
        config()->set('autocache.models', [Post::class]);

        $this->artisan('autocache:warm', ['--all' => true])->assertSuccessful();

        $selects = $this->countSelects(fn () => Post::all());
        $this->assertSame(0, $selects);
    }

    public function test_warm_command_requires_a_model_or_all(): void
    {
        $this->artisan('autocache:warm')->assertFailed();
    }

    public function test_stats_can_be_reset(): void
    {
        config()->set('autocache.stats', true);

        Post::all(); // one miss
        $this->assertGreaterThan(0, AutoCache::stats()['misses']);

        AutoCache::resetStats();

        $this->assertSame(['hits' => 0, 'misses' => 0], AutoCache::stats());
    }

    public function test_stats_reset_command_runs(): void
    {
        config()->set('autocache.stats', true);

        Post::all();

        $this->artisan('autocache:stats', ['--reset' => true])->assertSuccessful();

        $this->assertSame(['hits' => 0, 'misses' => 0], AutoCache::stats());
    }

    public function test_flush_command_runs(): void
    {
        $this->artisan('autocache:flush', ['model' => Post::class])
            ->assertSuccessful();
    }

    public function test_clear_command_runs(): void
    {
        config()->set('autocache.models', [Post::class]);

        $this->artisan('autocache:clear')->assertSuccessful();
    }
}
