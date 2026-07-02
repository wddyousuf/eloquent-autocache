<?php

namespace Hcs\LaraCache\Contracts;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The surface the {@see \Hcs\LaraCache\Traits\Cacheable} trait exposes and
 * that the query builders and cache manager rely on. Models using the trait
 * satisfy this contract implicitly; it exists chiefly to give static analysis
 * a precise type for those cross-references.
 */
interface Cacheable
{
    public function cacheIsEnabled(): bool;

    public function cacheMode(): string;

    public function cacheUsesTags(): bool;

    public function cacheKeyFor(QueryBuilder $query, array $columns = ['*'], string $type = 'get'): string;

    public function cacheKeyForCustom(string $name, string $type = 'select'): string;

    public function rememberInCache(string $key, Closure $callback, ?int $ttlOverride = null, bool $hasTtlOverride = false): mixed;

    public function flushCache(): void;

    public function flushQueriesOnly(): void;

    public function flushForSingleRow(mixed $id): void;

    public function runFlush(Closure $flush): void;

    public function rawCacheStore(): CacheRepository;

    public function cacheStore(): CacheRepository;

    public function cachePrefix(): string;

    public function getCacheVersion(): int;

    public function rowCacheEnabled(): bool;

    public function rememberRowInCache(mixed $id, Closure $callback, ?int $ttlOverride = null, bool $hasTtlOverride = false): mixed;

    /** @return array<int, \Illuminate\Contracts\Database\Query\Builder|Builder> */
    public function cacheWarmupQueries(): array;
}
