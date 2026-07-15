<?php

namespace Wddyousuf\AutoCache\Tests;

use Wddyousuf\AutoCache\Tests\Models\Post;

/**
 * Reproduces GitHub issue #4 and related failures under Laravel 13's default
 * `cache.serializable_classes => false`, which forbids unserializing ANY class
 * (including stdClass and Eloquent models) back out of a serializing cache store.
 */
class SerializableClassesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Mirror a default Laravel 13 app: a serializing store (file) plus the
        // strict allow-list that ships in config/cache.php.
        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file', [
            'driver' => 'file',
            'path' => storage_path('framework/cache/repro'),
        ]);
        $app['config']->set('cache.serializable_classes', false);
        $app['config']->set('autocache.store', 'file');
        $app['config']->set('autocache.use_tags', false);
    }

    protected function tearDown(): void
    {
        $dir = storage_path('framework/cache/repro');
        if (is_dir($dir)) {
            exec('rm -rf '.escapeshellarg($dir));
        }
        parent::tearDown();
    }

    public function test_pagination_count_survives_cache_round_trip(): void
    {
        // First call populates the cache, second reads it back.
        Post::where('published', true)->paginate(1);
        $page = Post::where('published', true)->paginate(1);

        $this->assertSame(2, $page->total());
    }

    public function test_cached_get_returns_correct_data_after_round_trip(): void
    {
        Post::query()->get();
        $rows = Post::query()->get();

        $this->assertCount(2, $rows);
        $this->assertSame('first', $rows->first()->title);
        $this->assertInstanceOf(Post::class, $rows->first());
    }

    public function test_cached_aggregate_survives_round_trip(): void
    {
        Post::query()->count();
        $count = Post::query()->count();

        $this->assertSame(2, $count);
    }

    public function test_row_cache_find_survives_round_trip(): void
    {
        Post::find(1);
        $post = Post::find(1);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('first', $post->title);
    }

    public function test_cached_get_does_not_leak_incomplete_class_attributes(): void
    {
        // Before the fix, reading stdClass rows back under serializable_classes
        // => false smuggled a junk "__PHP_Incomplete_Class_Name" attribute into
        // each hydrated model. Guard against that silent corruption returning.
        Post::query()->get();
        $post = Post::query()->get()->first();

        $this->assertArrayNotHasKey('__PHP_Incomplete_Class_Name', $post->getAttributes());
        $this->assertSame(['id', 'title', 'published', 'views', 'deleted_at'], array_keys($post->getAttributes()));
    }

    public function test_stale_while_revalidate_path_survives_round_trip(): void
    {
        // flexible() stores and returns the value unconditionally, bypassing the
        // normal hit/miss branch — exercise it under the strict allow-list too.
        config(['autocache.ttl' => 60, 'autocache.swr' => 30]);

        Post::where('published', true)->paginate(1);
        $page = Post::where('published', true)->paginate(1);

        $this->assertSame(2, $page->total());
        $this->assertSame('first', $page->items()[0]->title);
    }
}
