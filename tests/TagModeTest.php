<?php

namespace Wddyousuf\AutoCache\Tests;

use Wddyousuf\AutoCache\Tests\Models\Post;

/**
 * Exercises the tag-based invalidation path (the array store is taggable),
 * as opposed to the version-counter path used by the rest of the suite.
 */
class TagModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('autocache.use_tags', 'auto');
    }

    public function test_model_uses_tags_with_a_taggable_store(): void
    {
        $this->assertTrue((new Post)->cacheUsesTags());
    }

    public function test_reads_are_cached_in_tag_mode(): void
    {
        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(1, $selects);
    }

    public function test_writes_flush_tags(): void
    {
        $this->assertCount(2, Post::all());

        Post::create(['title' => 'tagged']);

        $this->assertCount(3, Post::all());
    }
}
