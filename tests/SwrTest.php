<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\Cache;
use Wddyousuf\AutoCache\Tests\Models\Post;

class SwrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! method_exists(Cache::store('array'), 'flexible')) {
            $this->markTestSkipped('Cache::flexible() requires Laravel 11+.');
        }
    }

    public function test_stale_while_revalidate_serves_from_cache(): void
    {
        config()->set('autocache.ttl', 60);  // finite TTL required for SWR
        config()->set('autocache.swr', 30);

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(1, $selects);
    }

    public function test_writes_still_flush_under_swr(): void
    {
        config()->set('autocache.ttl', 60);
        config()->set('autocache.swr', 30);

        Post::all();
        Post::create(['title' => 'x']);

        $this->assertCount(3, Post::all());
    }

    public function test_swr_does_not_bypass_the_max_rows_guard(): void
    {
        config()->set('autocache.ttl', 60);
        config()->set('autocache.swr', 30);
        config()->set('autocache.max_rows', 1); // we have 2 posts

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects, 'Oversized result must not be stored, even under SWR.');
    }
}
