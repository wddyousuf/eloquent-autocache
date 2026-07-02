<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\Tests\Models\AutoPost;
use Hcs\LaraCache\Tests\Models\OptInPost;
use Hcs\LaraCache\Tests\Models\Post;

class PerModelModeTest extends TestCase
{
    public function test_opt_in_model_is_not_cached_under_global_auto_mode(): void
    {
        $selects = $this->countSelects(function () {
            OptInPost::all();
            OptInPost::all();
        });

        $this->assertSame(2, $selects);
    }

    public function test_opt_in_model_caches_when_opted_in(): void
    {
        $selects = $this->countSelects(function () {
            OptInPost::cache()->get();
            OptInPost::cache()->get();
        });

        $this->assertSame(1, $selects);
    }

    public function test_opt_in_model_find_is_not_row_cached_without_opting_in(): void
    {
        $selects = $this->countSelects(function () {
            OptInPost::find(1);
            OptInPost::find(1);
        });

        $this->assertSame(2, $selects);
    }

    public function test_auto_model_caches_under_global_opt_in_mode(): void
    {
        config(['laracache.mode' => 'opt-in']);

        $selects = $this->countSelects(function () {
            AutoPost::all();
            AutoPost::all();
        });

        $this->assertSame(1, $selects);
    }

    public function test_model_without_override_follows_the_global_mode(): void
    {
        config(['laracache.mode' => 'opt-in']);

        $selects = $this->countSelects(function () {
            Post::all();
            Post::all();
        });

        $this->assertSame(2, $selects);
    }
}
