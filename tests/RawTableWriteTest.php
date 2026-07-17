<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\DB;
use Wddyousuf\AutoCache\Tests\Models\Post;

/**
 * DB::table('posts')->update() bypasses both the model and its cache-aware
 * builder, so AutoCache cannot see it on its own. Listing the table in the
 * invalidation map makes the query-stream listener catch it — the same
 * mechanism that handles pivot writes, since it matches on the raw table name.
 */
class RawTableWriteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('autocache.pivot_invalidation.map', [
            'posts' => [Post::class],
        ]);
    }

    public function test_a_raw_db_table_write_is_caught_when_the_table_is_watched(): void
    {
        // Warm.
        $this->assertSame('first', Post::query()->where('id', 1)->first()->title);
        $this->assertSame(0, $this->countSelects(fn () => Post::query()->where('id', 1)->first()));

        // Raw write — never touches the model or its builder.
        DB::table('posts')->where('id', 1)->update(['title' => 'raw-edit']);

        // The listener flushed Post, so the next read is fresh.
        $this->assertSame('raw-edit', Post::query()->where('id', 1)->first()->title);
    }
}
