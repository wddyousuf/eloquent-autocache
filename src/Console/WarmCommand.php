<?php

namespace Hcs\LaraCache\Console;

use Hcs\LaraCache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class WarmCommand extends Command
{
    protected $signature = 'laracache:warm {model : Model class (FQCN or App\\Models\\ short name)}';

    protected $description = 'Pre-populate a model\'s cache by running its warm-up queries';

    public function handle(CacheManager $cache): int
    {
        $class = $this->resolveModelClass($this->argument('model'));

        if ($class === null) {
            $this->components->error("Model [{$this->argument('model')}] not found.");

            return self::FAILURE;
        }

        $count = $cache->warm($class);
        $this->components->info("Warmed {$count} quer(ies) for [{$class}].");

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
