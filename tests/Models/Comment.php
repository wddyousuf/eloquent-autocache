<?php

namespace Wddyousuf\AutoCache\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wddyousuf\AutoCache\Traits\Cacheable;

class Comment extends Model
{
    use Cacheable;

    protected $guarded = [];

    public $timestamps = false;

    /** Flushing a comment should also flush its parent Post's cache. */
    protected $flushRelated = ['post'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
