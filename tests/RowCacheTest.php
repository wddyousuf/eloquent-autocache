<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Tests\Models\Post;

class RowCacheTest extends TestCase
{
    public function test_find_is_row_cached(): void
    {
        $selects = $this->countSelects(function () {
            Post::find(1);
            Post::find(1);
        });

        $this->assertSame(1, $selects);
    }

    public function test_find_survives_a_write_to_another_row(): void
    {
        Post::find(1);
        Post::find(2); // warm both rows

        // Surgical single-row update to row 2.
        Post::where('id', 2)->update(['title' => 'changed']);

        // Row 1's find cache is untouched; row 2's was dropped.
        $this->assertSame(0, $this->countSelects(fn () => Post::find(1)));
        $this->assertSame(1, $this->countSelects(fn () => Post::find(2)));
    }

    public function test_find_survives_an_insert(): void
    {
        Post::find(1); // warm

        Post::create(['title' => 'brand new']); // insert flushes queries only

        $this->assertSame(0, $this->countSelects(fn () => Post::find(1)));
    }

    public function test_bulk_update_drops_all_row_caches(): void
    {
        Post::find(1); // warm

        Post::query()->update(['views' => 9]); // no where → full flush

        $this->assertSame(1, $this->countSelects(fn () => Post::find(1)));
    }

    public function test_deleting_a_row_keeps_other_rows_cached(): void
    {
        Post::find(1);
        Post::find(2);

        Post::where('id', 1)->delete();

        $this->assertSame(0, $this->countSelects(fn () => Post::find(2)));
    }

    public function test_missing_row_is_not_cached(): void
    {
        $this->assertNull(Post::find(999));

        // Not cached, so it still hits the DB (and would see a later insert).
        $this->assertSame(1, $this->countSelects(fn () => Post::find(999)));
    }

    public function test_non_canonical_find_falls_back_but_still_works(): void
    {
        $this->assertNotNull(Post::where('published', true)->find(1));
        $this->assertNull(Post::where('published', false)->find(1));
    }
}
