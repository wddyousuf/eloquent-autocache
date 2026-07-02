<?php

namespace Wddyousuf\AutoCache\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Wddyousuf\AutoCache\CacheManager;

class FlushCommand extends Command
{
    protected $signature = 'autocache:flush {model : Model class (FQCN or App\\Models\\ short name)}';

    protected $description = 'Flush the cached queries for a single model';

    public function handle(CacheManager $cache): int
    {
        $class = $this->resolveModelClass($this->argument('model'));

        if ($class === null) {
            $this->components->error("Model [{$this->argument('model')}] not found.");

            return self::FAILURE;
        }

        $cache->flush($class);
        $this->components->info("Flushed cache for [{$class}].");

        return self::SUCCESS;
    }

    /**
     * @return class-string<Model>|null
     */
    protected function resolveModelClass(string $model): ?string
    {
        foreach ([$model, 'App\\Models\\'.$model, 'App\\'.$model] as $candidate) {
            if (is_subclass_of($candidate, Model::class)) {
                return $candidate;
            }
        }

        return null;
    }
}
