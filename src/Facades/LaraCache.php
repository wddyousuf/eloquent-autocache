<?php

namespace Hcs\LaraCache\Facades;

use Hcs\LaraCache\CacheManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void flush(\Illuminate\Database\Eloquent\Model|string $model)
 * @method static int clear()
 * @method static int warm(\Illuminate\Database\Eloquent\Model|string $model)
 * @method static array warmAll()
 * @method static array stats(\Illuminate\Database\Eloquent\Model|string|null $model = null)
 * @method static void resetStats(\Illuminate\Database\Eloquent\Model|string|null $model = null)
 * @method static array registeredModels()
 *
 * @see CacheManager
 */
class LaraCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laracache';
    }
}
