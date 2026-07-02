<?php

namespace Hcs\LaraCache\Console;

use Hcs\LaraCache\CacheManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class WarmCommand extends Command
{
    protected $signature = 'laracache:warm
        {model? : Model class (FQCN or App\\Models\\ short name)}
        {--all : Warm every registered model}';

    protected $description = 'Pre-populate a model\'s cache by running its warm-up queries';

    public function handle(CacheManager $cache): int
    {
        if ($this->option('all')) {
            return $this->warmAll($cache);
        }

        $model = $this->argument('model');

        if ($model === null) {
            $this->components->error('Provide a model class or use --all.');

            return self::FAILURE;
        }

        $class = $this->resolveModelClass($model);

        if ($class === null) {
            $this->components->error("Model [{$model}] not found.");

            return self::FAILURE;
        }

        $count = $cache->warm($class);
        $this->components->info("Warmed {$count} quer(ies) for [{$class}].");

        return self::SUCCESS;
    }

    protected function warmAll(CacheManager $cache): int
    {
        $results = $cache->warmAll();

        if ($results === []) {
            $this->components->warn(
                'No cacheable models found. List them in config("laracache.models") '
                .'so this command can discover them.'
            );

            return self::SUCCESS;
        }

        foreach ($results as $class => $count) {
            $this->components->info("Warmed {$count} quer(ies) for [{$class}].");
        }

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
