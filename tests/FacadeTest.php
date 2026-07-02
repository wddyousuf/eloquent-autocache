<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Facades\LaraCache;
use Hcs\LaraCache\Tests\Models\Post;
use Illuminate\Support\Facades\DB;

class FacadeTest extends TestCase
{
    public function test_facade_flush_invalidates_a_model(): void
    {
        $this->assertCount(2, Post::all()); // warm

        // Insert straight through the connection so LaraCache never sees the
        // write; the cache is now stale until we flush it manually.
        DB::table('posts')->insert(['title' => 'sneaky', 'published' => true, 'views' => 0]);
        $this->assertCount(2, Post::all(), 'Cache should still be stale here.');

        LaraCache::flush(Post::class);

        $this->assertCount(3, Post::all());
    }

    public function test_facade_clear_flushes_registered_models(): void
    {
        Post::all(); // boots + registers the model

        $this->assertContains(Post::class, LaraCache::registeredModels());
        $this->assertGreaterThanOrEqual(1, LaraCache::clear());
    }

    public function test_warm_command_populates_the_cache(): void
    {
        config()->set('laracache.models', [Post::class]);

        $this->artisan('laracache:warm', ['model' => Post::class])
            ->assertSuccessful();

        // Cache is now warm: a subsequent read performs no SELECT.
        $selects = $this->countSelects(fn () => Post::all());
        $this->assertSame(0, $selects);
    }

    public function test_warm_all_command_populates_registered_models(): void
    {
        config()->set('laracache.models', [Post::class]);

        $this->artisan('laracache:warm', ['--all' => true])->assertSuccessful();

        $selects = $this->countSelects(fn () => Post::all());
        $this->assertSame(0, $selects);
    }

    public function test_warm_command_requires_a_model_or_all(): void
    {
        $this->artisan('laracache:warm')->assertFailed();
    }

    public function test_stats_can_be_reset(): void
    {
        config()->set('laracache.stats', true);

        Post::all(); // one miss
        $this->assertGreaterThan(0, LaraCache::stats()['misses']);

        LaraCache::resetStats();

        $this->assertSame(['hits' => 0, 'misses' => 0], LaraCache::stats());
    }

    public function test_stats_reset_command_runs(): void
    {
        config()->set('laracache.stats', true);

        Post::all();

        $this->artisan('laracache:stats', ['--reset' => true])->assertSuccessful();

        $this->assertSame(['hits' => 0, 'misses' => 0], LaraCache::stats());
    }

    public function test_flush_command_runs(): void
    {
        $this->artisan('laracache:flush', ['model' => Post::class])
            ->assertSuccessful();
    }

    public function test_clear_command_runs(): void
    {
        config()->set('laracache.models', [Post::class]);

        $this->artisan('laracache:clear')->assertSuccessful();
    }
}
