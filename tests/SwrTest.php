<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Tests\Models\Post;
use Illuminate\Support\Facades\Cache;

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
        config()->set('laracache.ttl', 60);  // finite TTL required for SWR
        config()->set('laracache.swr', 30);

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(1, $selects);
    }

    public function test_writes_still_flush_under_swr(): void
    {
        config()->set('laracache.ttl', 60);
        config()->set('laracache.swr', 30);

        Post::all();
        Post::create(['title' => 'x']);

        $this->assertCount(3, Post::all());
    }
}
