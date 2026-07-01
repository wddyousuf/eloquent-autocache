<?php

namespace Hcs\LaraCache\Tests;

use Hcs\LaraCache\LaraCacheServiceProvider;
use Hcs\LaraCache\Tests\Models\Post;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaraCacheServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('laracache.ttl', null);
        // Deterministic defaults for the core suite; individual tests opt in.
        $app['config']->set('laracache.use_tags', false);
        $app['config']->set('laracache.ttl_jitter', 0);
        $app['config']->set('laracache.lock_for', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->boolean('published')->default(true);
            $table->integer('views')->default(0);
            $table->softDeletes();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->string('body');
        });

        Post::insert([
            ['title' => 'first', 'published' => true, 'views' => 1],
            ['title' => 'second', 'published' => true, 'views' => 2],
        ]);
    }

    /** Count how many SQL SELECTs actually hit the database. */
    protected function countSelects(callable $callback): int
    {
        $count = 0;

        DB::listen(function ($query) use (&$count) {
            if (str_starts_with(strtolower(trim($query->sql)), 'select')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }
}
