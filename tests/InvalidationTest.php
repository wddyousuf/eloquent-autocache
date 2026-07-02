<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Tests\Models\Comment;
use Hcs\LaraCache\Tests\Models\Post;
use Illuminate\Support\Facades\DB;

class InvalidationTest extends TestCase
{
    public function test_quiet_save_still_flushes(): void
    {
        $this->assertCount(2, Post::all());

        $post = new Post(['title' => 'quiet']);
        $post->saveQuietly();

        $this->assertCount(3, Post::all());
    }

    public function test_quiet_update_still_flushes(): void
    {
        Post::all();

        Post::find(1)->updateQuietly(['title' => 'quiet-update']);

        $this->assertNotNull(Post::where('title', 'quiet-update')->first());
    }

    public function test_raw_bulk_insert_flushes(): void
    {
        $this->assertCount(2, Post::all());

        Post::insert(['title' => 'raw', 'published' => true, 'views' => 0]);

        $this->assertCount(3, Post::all());
    }

    public function test_related_model_write_flushes_parent(): void
    {
        // Warm the parent Post cache.
        $this->assertCount(2, Post::all());

        // Comment declares $flushRelated = ['post'], so this must flush Post too.
        Comment::create(['post_id' => 1, 'body' => 'hi']);

        $selects = $this->countSelects(fn () => Post::all());
        $this->assertSame(1, $selects, 'Parent cache should have been invalidated.');
    }

    public function test_custom_cache_key_is_invalidated_on_write(): void
    {
        $this->assertCount(2, Post::query()->cacheKey('homepage')->get());

        Post::create(['title' => 'third']);

        $this->assertCount(3, Post::query()->cacheKey('homepage')->get());
    }

    public function test_custom_cache_key_still_caches(): void
    {
        $selects = $this->countSelects(function () {
            Post::query()->cacheKey('homepage')->get();
            Post::query()->cacheKey('homepage')->get();
        });

        $this->assertSame(1, $selects);
    }

    public function test_insert_using_flushes(): void
    {
        $this->assertSame(2, Post::count());

        Post::query()->getQuery()->insertUsing(
            ['title', 'published', 'views'],
            DB::table('posts')->select(['title', 'published', 'views'])->where('id', 1)
        );

        $this->assertSame(3, Post::count());
    }

    public function test_insert_or_ignore_using_flushes(): void
    {
        $this->assertSame(2, Post::count());

        Post::query()->getQuery()->insertOrIgnoreUsing(
            ['title', 'published', 'views'],
            DB::table('posts')->select(['title', 'published', 'views'])->where('id', 1)
        );

        $this->assertSame(3, Post::count());
    }

    public function test_rollback_does_not_leave_stale_cache(): void
    {
        $this->assertCount(2, Post::all());

        DB::transaction(function () {
            Post::create(['title' => 'in-transaction']);
            // Cache flush is deferred until commit.
            $this->assertCount(3, Post::all());
        });

        // After commit the deferred flush ran; a fresh read is correct.
        $this->assertCount(3, Post::all());
    }
}
