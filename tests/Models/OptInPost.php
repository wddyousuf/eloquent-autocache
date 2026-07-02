<?php

namespace Hcs\LaraCache\Tests\Models;

/** A Post that opts into caching per query, regardless of the global mode. */
class OptInPost extends Post
{
    protected $table = 'posts';

    protected $cacheMode = 'opt-in';
}
