<?php

namespace Wddyousuf\AutoCache\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when a query is not in the cache and must hit the database.
 */
class CacheMissed
{
    public function __construct(
        public Model $model,
        public string $key,
    ) {}
}
