<?php

namespace Wddyousuf\AutoCache\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wddyousuf\AutoCache\Traits\Cacheable;

class Post extends Model
{
    use Cacheable;
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = false;

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
