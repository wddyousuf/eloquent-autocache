<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Facades\LaraCache;
use Hcs\LaraCache\Tests\Models\Post;

class FakeTest extends TestCase
{
    public function test_fake_records_a_flush_on_write(): void
    {
        $fake = LaraCache::fake();

        Post::create(['title' => 'x']);

        $fake->assertFlushed(Post::class);
    }

    public function test_fake_records_hits_and_misses(): void
    {
        $fake = LaraCache::fake();

        Post::all(); // miss
        Post::all(); // hit

        $fake->assertMissed(Post::class);
        $fake->assertHit(Post::class);
    }

    public function test_fake_asserts_nothing_flushed_on_reads(): void
    {
        $fake = LaraCache::fake();

        Post::all();

        $fake->assertNothingFlushed();
        $fake->assertNotFlushed(Post::class);
    }
}
