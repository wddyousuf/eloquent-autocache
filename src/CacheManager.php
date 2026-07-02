<?php

namespace Hcs\LaraCache;

use Hcs\LaraCache\Contracts\Cacheable;
use Hcs\LaraCache\Facades\LaraCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Programmatic entry point behind the LaraCache facade: flush, clear, warm
 * and inspect stats for cacheable models.
 */
class CacheManager
{
    /** Guards against infinite recursion while co-flushing related models. */
    public static array $flushing = [];

    /** Models registered at boot time, keyed by class name. */
    protected static array $registered = [];

    /**
     * Record a cacheable model so console commands can discover it.
     */
    public static function register(string $class): void
    {
        static::$registered[$class] = $class;
    }

    /**
     * Reset per-request process-static state. Registered as an Octane request
     * listener so a long-lived worker never carries a half-finished flush
     * guard between requests.
     */
    public static function resetState(): void
    {
        static::$flushing = [];
    }

    /**
     * Swap the container/facade binding for a recording fake and return it,
     * so tests can assert on cache behaviour.
     */
    public static function fake(): LaraCacheFake
    {
        $fake = new LaraCacheFake;

        app()->instance('laracache', $fake);
        LaraCache::swap($fake);
        $fake->listen();

        return $fake;
    }

    /**
     * All known cacheable models: those booted this request plus any listed
     * in config.
     *
     * @return array<int, class-string<Model>>
     */
    public function registeredModels(): array
    {
        return array_values(array_unique(array_merge(
            array_values(static::$registered),
            (array) config('laracache.models', [])
        )));
    }

    /**
     * Flush the cache for a single model (instance or class name).
     */
    public function flush(Model|string $model): void
    {
        $this->resolve($model)->flushCache();
    }

    /**
     * Flush the cache for every registered model. Returns the count flushed.
     */
    public function clear(): int
    {
        $flushed = 0;

        foreach ($this->registeredModels() as $class) {
            if (is_subclass_of($class, Model::class)) {
                /** @var Model&Cacheable $instance */
                $instance = new $class;
                $instance->flushCache();
                $flushed++;
            }
        }

        return $flushed;
    }

    /**
     * Warm a model's cache by running its warm-up queries. Returns the number
     * of queries executed.
     */
    public function warm(Model|string $model): int
    {
        $instance = $this->resolve($model);
        $warmed = 0;

        foreach ($instance->cacheWarmupQueries() as $query) {
            $query->get();
            $warmed++;
        }

        return $warmed;
    }

    /**
     * Warm every registered model. Returns the queries warmed per model.
     *
     * @return array<class-string<Model>, int>
     */
    public function warmAll(): array
    {
        $results = [];

        foreach ($this->registeredModels() as $class) {
            if (is_subclass_of($class, Model::class)) {
                $results[$class] = $this->warm($class);
            }
        }

        return $results;
    }

    /**
     * Reset the hit/miss counters for one model, or (with no argument) the
     * global counters plus every registered model's counters.
     */
    public function resetStats(Model|string|null $model = null): void
    {
        if ($model !== null) {
            $instance = $this->resolve($model);
            $this->forgetStatKeys($instance->rawCacheStore(), $instance->cachePrefix());

            return;
        }

        $this->forgetStatKeys(Cache::store(config('laracache.store')), 'laracache');

        foreach ($this->registeredModels() as $class) {
            if (is_subclass_of($class, Model::class)) {
                /** @var Model&Cacheable $instance */
                $instance = new $class;
                $this->forgetStatKeys($instance->rawCacheStore(), $instance->cachePrefix());
            }
        }
    }

    protected function forgetStatKeys(CacheRepository $store, string $prefix): void
    {
        $store->forget("{$prefix}:stats:hits");
        $store->forget("{$prefix}:stats:misses");
    }

    /**
     * Hit/miss counters, globally or scoped to a single model.
     *
     * @return array{hits: int, misses: int}
     */
    public function stats(Model|string|null $model = null): array
    {
        if ($model !== null) {
            $instance = $this->resolve($model);
            $store = $instance->rawCacheStore();
            $prefix = $instance->cachePrefix();
        } else {
            $store = Cache::store(config('laracache.store'));
            $prefix = 'laracache';
        }

        return [
            'hits' => (int) $store->get("{$prefix}:stats:hits", 0),
            'misses' => (int) $store->get("{$prefix}:stats:misses", 0),
        ];
    }

    /**
     * @return Model&Cacheable
     */
    protected function resolve(Model|string $model): Model
    {
        /** @var Model&Cacheable */
        return is_string($model) ? new $model : $model;
    }
}
