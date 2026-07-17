<?php

namespace Wddyousuf\AutoCache\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A non-cacheable model (no Cacheable trait) used to prove the pivot listener
 * skips a misconfigured map entry instead of fataling on it.
 */
class PlainRecord extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
