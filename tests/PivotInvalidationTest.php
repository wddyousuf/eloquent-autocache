<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Wddyousuf\AutoCache\Tests\Models\PlainRecord;
use Wddyousuf\AutoCache\Tests\Models\Post;
use Wddyousuf\AutoCache\Tests\Models\Tag;

/**
 * A many-to-many write (sync/attach/detach) is a bare pivot statement that
 * never reaches a cacheable model's builder, so a cached relation read would go
 * stale. The configured pivot map registers a query-stream listener that flushes
 * the mapped models on any write to the pivot.
 */
class PivotInvalidationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('autocache.pivot_invalidation.map', [
            'post_tag' => [Post::class, Tag::class],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('tag_id');
        });
    }

    public function test_attaching_a_tag_invalidates_the_cached_relation(): void
    {
        $post = Post::query()->first();
        $tag = Tag::create(['name' => 'x']);

        $ids = fn () => $post->tags()->pluck('tags.id')->all();

        // Warm, then confirm the relation read is genuinely cached.
        $this->assertSame([], $ids());
        $this->assertSame(0, $this->countSelects(fn () => $ids()));

        $post->tags()->attach($tag->id);

        // If the listener did not flush, the cached empty result would persist.
        $this->assertSame([$tag->id], $ids());
    }

    public function test_detaching_a_tag_invalidates_the_cached_relation(): void
    {
        $post = Post::query()->first();
        $tag = Tag::create(['name' => 'y']);
        $post->tags()->attach($tag->id);

        $ids = fn () => $post->tags()->pluck('tags.id')->all();

        // Warm on the attached state.
        $this->assertSame([$tag->id], $ids());
        $this->assertSame(0, $this->countSelects(fn () => $ids()));

        $post->tags()->detach($tag->id);

        $this->assertSame([], $ids());
    }

    public function test_sync_invalidates_the_cached_relation(): void
    {
        $post = Post::query()->first();
        $keep = Tag::create(['name' => 'keep']);
        $drop = Tag::create(['name' => 'drop']);
        $post->tags()->attach([$keep->id, $drop->id]);

        $ids = fn () => $post->tags()->pluck('tags.id')->sort()->values()->all();

        $this->assertSame([$keep->id, $drop->id], $ids()); // warm

        $post->tags()->sync([$keep->id]);

        $this->assertSame([$keep->id], $ids());
    }

    public function test_every_mapped_model_is_flushed_not_just_one(): void
    {
        $post = Post::query()->first();
        $tag = Tag::create(['name' => 't']);

        // Warm a query cached under EACH mapped model: Tag (via the relation)
        // and Post (its own table).
        $tagIds = fn () => $post->tags()->pluck('tags.id')->all();
        $postIds = fn () => Post::query()->where('published', true)->pluck('id')->all();

        $this->assertSame([], $tagIds());
        $warmPosts = $postIds();
        $this->assertSame(0, $this->countSelects(fn () => $tagIds()));  // cached
        $this->assertSame(0, $this->countSelects(fn () => $postIds())); // cached

        $post->tags()->attach($tag->id);

        // Both caches were flushed by the single pivot write.
        $this->assertSame(1, $this->countSelects(fn () => $tagIds()));
        $this->assertSame(1, $this->countSelects(fn () => $postIds()));
        $this->assertSame([$tag->id], $tagIds());
        $this->assertSame($warmPosts, $postIds());
    }

    public function test_a_read_from_the_pivot_does_not_flush(): void
    {
        $post = Post::query()->first();
        $tag = Tag::create(['name' => 'r']);
        $post->tags()->attach($tag->id);

        $postIds = fn () => Post::query()->where('published', true)->pluck('id')->all();
        $postIds();

        // A SELECT touching the pivot is not a write and must not flush.
        $selects = $this->countSelects(function () use ($post, $postIds) {
            $post->tags()->get();   // reads post_tag
            $postIds();             // still cached
        });

        $this->assertSame(1, $selects); // only the relation read; Post stayed cached
    }

    public function test_a_non_cacheable_map_entry_is_skipped_not_fatal(): void
    {
        // PlainRecord lacks the Cacheable trait; the listener must skip it and
        // still flush the cacheable Post.
        config()->set('autocache.pivot_invalidation.map', [
            'post_tag' => [PlainRecord::class, Post::class],
        ]);

        $post = Post::query()->first();

        $postIds = fn () => Post::query()->where('published', true)->pluck('id')->all();
        $postIds();
        $this->assertSame(0, $this->countSelects(fn () => $postIds()));

        // No fatal, and Post is still flushed.
        DB::table('post_tag')->insert(['post_id' => $post->id, 'tag_id' => 1]);

        $this->assertSame(1, $this->countSelects(fn () => $postIds()));
    }
}
