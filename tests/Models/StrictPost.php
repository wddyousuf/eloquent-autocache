<?php

namespace Wddyousuf\AutoCache\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Wddyousuf\AutoCache\Traits\Cacheable;

/**
 * Same `posts` table, but opts out of in-transaction caching at the model level
 * to exercise the per-model $cacheInTransactions override.
 */
class StrictPost extends Model
{
    use Cacheable;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    protected $cacheInTransactions = false;
}
