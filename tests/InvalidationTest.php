<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\DB;
use Wddyousuf\AutoCache\Tests\Models\Comment;
use Wddyousuf\AutoCache\Tests\Models\Post;

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

    public function test_deferred_flush_runs_after_commit(): void
    {
        $this->assertCount(2, Post::all());

        DB::transaction(function () {
            Post::create(['title' => 'in-transaction']);
            // The write flushes immediately and re-flushes after commit; the
            // in-transaction read (default mode) re-caches the committed-to-be
            // value, which is correct on commit. See TransactionCachingTest for
            // the rollback case and strict mode.
            $this->assertCount(3, Post::all());
        });

        // After commit the deferred flush ran; a fresh read is correct.
        $this->assertCount(3, Post::all());
    }

    /**
     * Documents a known limitation: a raw DB::table() write touches neither the
     * model nor its cache-aware builder, so AutoCache cannot see it on its own.
     * Remedies: call AutoCache::flush(Model::class), or list the table in the
     * invalidation map (see RawTableWriteTest).
     */
    public function test_a_raw_db_table_write_is_not_seen_without_watching(): void
    {
        $this->assertSame('first', Post::query()->where('id', 1)->first()->title);

        DB::table('posts')->where('id', 1)->update(['title' => 'invisible']);

        // Stale: the cached read still returns the pre-write value.
        $this->assertSame('first', Post::query()->where('id', 1)->first()->title);
    }
}
