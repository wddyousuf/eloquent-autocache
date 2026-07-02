<?php

namespace Wddyousuf\AutoCache\Tests;

use Wddyousuf\AutoCache\Tests\Models\Post;

class CacheableTest extends TestCase
{
    public function test_repeated_reads_hit_the_database_only_once(): void
    {
        $selects = $this->countSelects(function () {
            $this->assertCount(2, Post::all());
            $this->assertCount(2, Post::all());
            $this->assertCount(2, Post::all());
        });

        $this->assertSame(1, $selects);
    }

    public function test_first_and_find_are_cached(): void
    {
        $selects = $this->countSelects(function () {
            Post::where('title', 'first')->first();
            Post::where('title', 'first')->first();
            Post::find(1);
            Post::find(1);
        });

        // One SELECT for the where-first, one for the find; each cached after.
        $this->assertSame(2, $selects);
    }

    public function test_pluck_is_cached(): void
    {
        $selects = $this->countSelects(function () {
            $this->assertSame(['first', 'second'], Post::pluck('title')->all());
            $this->assertSame(['first', 'second'], Post::pluck('title')->all());
        });

        $this->assertSame(1, $selects);
    }

    public function test_creating_flushes_the_cache(): void
    {
        $this->assertCount(2, Post::all());

        Post::create(['title' => 'third']);

        $this->assertCount(3, Post::all());
    }

    public function test_updating_flushes_the_cache(): void
    {
        Post::all();

        Post::where('title', 'first')->first()->update(['title' => 'renamed']);

        $this->assertNotNull(Post::where('title', 'renamed')->first());
    }

    public function test_deleting_flushes_the_cache(): void
    {
        $this->assertCount(2, Post::all());

        Post::where('title', 'first')->first()->delete();

        $this->assertCount(1, Post::all());
    }

    public function test_bulk_update_flushes_the_cache(): void
    {
        Post::all();

        Post::query()->update(['title' => 'bulk']);

        $this->assertCount(2, Post::where('title', 'bulk')->get());
    }

    public function test_bulk_delete_flushes_the_cache(): void
    {
        $this->assertCount(2, Post::all());

        Post::where('title', 'first')->delete();

        $this->assertCount(1, Post::all());
    }

    public function test_soft_delete_and_restore_flush_the_cache(): void
    {
        $this->assertCount(2, Post::all());

        $post = Post::where('title', 'first')->first();
        $post->delete();
        $this->assertCount(1, Post::all());

        $post->restore();
        $this->assertCount(2, Post::all());
    }

    public function test_cache_for_null_caches_forever(): void
    {
        config(['autocache.ttl' => 1]);

        Post::cacheFor(null)->get();

        $selects = $this->countSelects(function () {
            $this->travel(5)->seconds();
            Post::cacheFor(null)->get();
        });

        $this->assertSame(0, $selects, 'cacheFor(null) must outlive the default TTL');
    }

    public function test_increment_flushes_the_cache(): void
    {
        $post = Post::find(1);
        $this->assertSame(1, $post->views);

        $post->increment('views');

        $this->assertSame(2, Post::find(1)->views);
    }
}
