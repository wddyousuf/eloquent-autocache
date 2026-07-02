<?php

namespace Wddyousuf\AutoCache\Tests\Models;

/** A Post that caches every read, regardless of the global mode. */
class AutoPost extends Post
{
    protected $table = 'posts';

    protected $cacheMode = 'auto';
}
