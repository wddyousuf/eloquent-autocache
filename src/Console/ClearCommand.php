<?php

namespace Wddyousuf\AutoCache\Console;

use Illuminate\Console\Command;
use Wddyousuf\AutoCache\CacheManager;

class ClearCommand extends Command
{
    protected $signature = 'autocache:clear';

    protected $description = 'Flush cached queries for every registered cacheable model';

    public function handle(CacheManager $cache): int
    {
        $count = $cache->clear();

        if ($count === 0) {
            $this->components->warn(
                'No cacheable models found. List them in config("autocache.models") '
                .'so this command can discover them.'
            );

            return self::SUCCESS;
        }

        $this->components->info("Flushed cache for {$count} model(s).");

        return self::SUCCESS;
    }
}
