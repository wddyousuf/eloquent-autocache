<?php

namespace Wddyousuf\AutoCache\Facades;

use Illuminate\Support\Facades\Facade;
use Wddyousuf\AutoCache\CacheManager;

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
class AutoCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'autocache';
    }
}
