<?php

namespace Wddyousuf\AutoCache\Tests;

use Wddyousuf\AutoCache\Tests\Models\Post;

class AggregateTest extends TestCase
{
    public function test_count_is_cached(): void
    {
        $selects = $this->countSelects(function () {
            $this->assertSame(2, Post::count());
            $this->assertSame(2, Post::count());
        });

        $this->assertSame(1, $selects);
    }

    public function test_count_is_flushed_on_write(): void
    {
        $this->assertSame(2, Post::count());

        Post::create(['title' => 'third']);

        $this->assertSame(3, Post::count());
    }

    public function test_sum_and_exists_are_cached(): void
    {
        $selects = $this->countSelects(function () {
            $this->assertSame(3, (int) Post::sum('views'));
            $this->assertSame(3, (int) Post::sum('views'));
            $this->assertTrue(Post::where('title', 'first')->exists());
            $this->assertTrue(Post::where('title', 'first')->exists());
        });

        $this->assertSame(2, $selects);
    }

    public function test_pagination_count_query_is_cached(): void
    {
        $selects = $this->countSelects(function () {
            Post::paginate(1);
            Post::paginate(1);
        });

        // First call: 1 count + 1 page select. Second call: both cached.
        $this->assertSame(2, $selects);
    }
}
