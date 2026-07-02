<?php

namespace Wddyousuf\AutoCache\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Wddyousuf\AutoCache\CacheManager;

class StatsCommand extends Command
{
    protected $signature = 'autocache:stats
        {model? : Optional model to scope the stats to}
        {--reset : Reset the counters instead of displaying them}';

    protected $description = 'Show AutoCache hit/miss statistics';

    public function handle(CacheManager $cache): int
    {
        $target = null;

        if ($model = $this->argument('model')) {
            $target = $this->resolveModelClass($model);

            if ($target === null) {
                $this->components->error("Model [{$model}] not found.");

                return self::FAILURE;
            }
        }

        if ($this->option('reset')) {
            $cache->resetStats($target);
            $this->components->info('Statistics reset for '.($target ?? 'all models').'.');

            return self::SUCCESS;
        }

        if (! config('autocache.stats', false)) {
            $this->components->warn('Stats are disabled. Set AUTOCACHE_STATS=true to collect them.');

            return self::SUCCESS;
        }

        $stats = $cache->stats($target);
        $total = $stats['hits'] + $stats['misses'];
        $rate = $total > 0 ? round($stats['hits'] / $total * 100, 1) : 0.0;

        $this->table(
            ['Scope', 'Hits', 'Misses', 'Hit rate'],
            [[$target ?? 'global', $stats['hits'], $stats['misses'], "{$rate}%"]]
        );

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
