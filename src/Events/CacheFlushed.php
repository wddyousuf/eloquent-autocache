<?php

namespace Wddyousuf\AutoCache\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when a model's cached queries are invalidated.
 */
class CacheFlushed
{
    public function __construct(
        public Model $model,
    ) {}
}
