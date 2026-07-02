<?php

namespace Wddyousuf\AutoCache\Tests;

use Wddyousuf\AutoCache\CacheManager;
use Wddyousuf\AutoCache\Tests\Models\Post;

class OctaneStateTest extends TestCase
{
    public function test_reset_state_clears_a_leaked_flush_guard(): void
    {
        // Simulate a worker whose flush guard leaked from a prior request
        // (e.g. a fatal mid-flush). Writes would otherwise be silently skipped.
        CacheManager::$flushing = [Post::class];

        CacheManager::resetState();

        $this->assertSame([], CacheManager::$flushing);
    }

    public function test_flushing_works_again_after_reset(): void
    {
        Post::all(); // warm

        CacheManager::$flushing = [Post::class]; // leaked guard
        CacheManager::resetState();

        Post::create(['title' => 'x']);

        $this->assertCount(3, Post::all());
    }
}
