<?php

namespace Hcs\LaraCache\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when a cached query result is served from the cache.
 */
class CacheHit
{
    public function __construct(
        public Model $model,
        public string $key,
    ) {}
}
