<?php

namespace Wddyousuf\AutoCache\Tests;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Wddyousuf\AutoCache\Tests\Models\Post;

/**
 * Reads issued while a transaction is open can observe not-yet-committed writes.
 * `cache_in_transactions` controls whether those reads may populate the cache.
 */
class TransactionCachingTest extends TestCase
{
    public function test_reads_inside_a_transaction_are_cached_by_default(): void
    {
        config()->set('autocache.cache_in_transactions', true);

        $selects = $this->countSelects(function () {
            DB::transaction(function () {
                Post::where('published', true)->get();
                Post::where('published', true)->get();
            });
        });

        // Second identical read is served from cache — keeps caching working
        // under RefreshDatabase, which wraps every test in a transaction.
        $this->assertSame(1, $selects);
    }

    public function test_strict_mode_bypasses_the_cache_inside_a_transaction(): void
    {
        config()->set('autocache.cache_in_transactions', false);

        $selects = $this->countSelects(function () {
            DB::transaction(function () {
                Post::where('published', true)->get();
                Post::where('published', true)->get();
            });
        });

        // Both reads hit the database — nothing is cached while the transaction
        // is open.
        $this->assertSame(2, $selects);
    }

    public function test_strict_mode_leaves_no_stale_cache_after_rollback(): void
    {
        config()->set('autocache.cache_in_transactions', false);

        // Warm outside the transaction.
        $this->assertCount(2, Post::all());

        try {
            DB::transaction(function () {
                Post::create(['title' => 'doomed']);
                // Observes the uncommitted row but does not cache it (strict).
                $this->assertCount(3, Post::all());

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        // The write's immediate flush cleared the warm entry, and the
        // in-transaction read never re-populated it, so the post-rollback read
        // is correct rather than a stale 3.
        $this->assertCount(2, Post::all());
    }
}
