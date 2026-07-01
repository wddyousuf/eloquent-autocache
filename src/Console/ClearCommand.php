<?php

namespace Hcs\LaraCache\Console;

use Hcs\LaraCache\CacheManager;
use Illuminate\Console\Command;

class ClearCommand extends Command
{
    protected $signature = 'laracache:clear';

    protected $description = 'Flush cached queries for every registered cacheable model';

    public function handle(CacheManager $cache): int
    {
        $count = $cache->clear();

        if ($count === 0) {
            $this->components->warn(
                'No cacheable models found. List them in config("laracache.models") '
                .'so this command can discover them.'
            );

            return self::SUCCESS;
        }

        $this->components->info("Flushed cache for {$count} model(s).");

        return self::SUCCESS;
    }
}
