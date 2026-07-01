<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Tests\Models\Post;

class OptInModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laracache.mode', 'opt-in');
    }

    public function test_reads_are_not_cached_without_opting_in(): void
    {
        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects);
    }

    public function test_reads_are_cached_when_opted_in(): void
    {
        $selects = $this->countSelects(function () {
            Post::cache()->get();
            Post::cache()->get();
        });

        $this->assertSame(1, $selects);
    }

    public function test_cache_for_also_opts_in(): void
    {
        $selects = $this->countSelects(function () {
            Post::cacheFor(60)->get();
            Post::cacheFor(60)->get();
        });

        $this->assertSame(1, $selects);
    }

    public function test_writes_still_flush_in_opt_in_mode(): void
    {
        Post::cache()->get();

        Post::create(['title' => 'x']);

        $this->assertCount(3, Post::cache()->get());
    }
}
