<?php

namespace Wddyousuf\AutoCache\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Wddyousuf\AutoCache\Contracts\Cacheable;

/**
 * Base query builder that caches reads and flushes on writes.
 *
 * Caching lives here — rather than on the Eloquent builder — because every
 * higher-level read (get, first, find, pluck, value, count, exists,
 * pagination count, …) ultimately funnels through this object's get(),
 * aggregate() or exists(). Likewise every persistence (save, bulk update,
 * raw insert, increment, …) funnels through its write methods, so a single
 * layer catches them all, including event-suppressing "quiet" writes.
 */
class CachedQueryBuilder extends QueryBuilder
{
    /**
     * The owning model, whose cache config and version drive caching.
     *
     * @var (Model&Cacheable)|null
     */
    public ?Model $cacheModel = null;

    protected bool $cacheEnabled = true;

    protected bool $cacheOptIn = false;

    protected ?int $cacheTtlOverride = null;

    /** Distinguishes cacheFor(null) ("forever") from "no override set". */
    protected bool $cacheTtlOverridden = false;

    protected ?string $cacheKeyOverride = null;

    /**
     * @param  (Model&Cacheable)|null  $model
     */
    public function setCacheModel(?Model $model): static
    {
        $this->cacheModel = $model;

        return $this;
    }

    public function withoutCache(): static
    {
        $this->cacheEnabled = false;

        return $this;
    }

    public function cacheFor(?int $ttl): static
    {
        $this->cacheTtlOverride = $ttl;
        $this->cacheTtlOverridden = true;
        $this->cacheOptIn = true;

        return $this;
    }

    public function getCacheTtlOverride(): ?int
    {
        return $this->cacheTtlOverride;
    }

    public function hasCacheTtlOverride(): bool
    {
        return $this->cacheTtlOverridden;
    }

    public function cache(): static
    {
        $this->cacheOptIn = true;

        return $this;
    }

    public function isCacheOptedIn(): bool
    {
        return $this->cacheOptIn;
    }

    public function cacheKey(?string $key): static
    {
        $this->cacheKeyOverride = $key;

        return $this;
    }

    /**
     * Whether this specific query is eligible for caching right now.
     */
    protected function cacheable(): bool
    {
        if ($this->cacheModel === null) {
            return false;
        }

        if ($this->cacheModel->cacheMode() === 'opt-in' && ! $this->cacheOptIn) {
            return false;
        }

        return $this->cacheEnabled
            && $this->cacheModel->cacheIsEnabled()
            && ! $this->queryIsVolatile()
            && ! $this->blockedByOpenTransaction();
    }

    /**
     * In strict mode (`cache_in_transactions` = false) a read issued while a
     * transaction is open bypasses the cache entirely, so a value observed
     * before commit can never be served from — or written to — the cache and
     * then survive a rollback.
     */
    protected function blockedByOpenTransaction(): bool
    {
        if ($this->cacheModel === null || $this->cacheModel->cacheInTransactions()) {
            return false;
        }

        $connection = $this->cacheModel->getConnection();

        return method_exists($connection, 'transactionLevel')
            && $connection->transactionLevel() > 0;
    }

    /**
     * Queries containing non-deterministic SQL must never be cached.
     */
    protected function queryIsVolatile(): bool
    {
        $sql = strtolower($this->toSql());

        foreach ((array) config('autocache.volatile_patterns', []) as $pattern) {
            if ($pattern !== '' && str_contains($sql, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // Reads
    // ---------------------------------------------------------------------

    /**
     * The single chokepoint every SELECT funnels through — get(), pluck(),
     * value(), the aggregates (count/sum/…) and the pagination count all call
     * runSelect(), so caching here covers them uniformly. Columns/aggregate
     * are already baked into the compiled SQL at this point, so the key
     * derived from toSql() + bindings distinguishes them.
     */
    protected function runSelect()
    {
        if (! $this->cacheable()) {
            return parent::runSelect();
        }

        $key = $this->cacheKeyOverride !== null
            ? $this->cacheModel->cacheKeyForCustom($this->cacheKeyOverride, 'select')
            : $this->cacheModel->cacheKeyFor($this, (array) ($this->columns ?? ['*']), 'select');

        // Cache rows as plain arrays, never as objects. Laravel 13 defaults
        // config('cache.serializable_classes') to false, so any serializing
        // store (file/database/redis) unserializes a stored stdClass row back
        // as an unusable __PHP_Incomplete_Class — which silently corrupts get()
        // hydration and throws outright in getCountForPagination(). Connection
        // always fetches rows as FETCH_OBJ stdClass, so re-objectifying on read
        // restores the exact shape every consumer expects.
        $rows = $this->cacheModel->rememberInCache(
            $key,
            fn () => array_map(fn ($row) => (array) $row, parent::runSelect()),
            $this->cacheTtlOverride,
            $this->cacheTtlOverridden
        );

        return array_map(fn ($row) => (object) $row, $rows);
    }

    public function exists()
    {
        if (! $this->cacheable()) {
            return parent::exists();
        }

        $key = $this->cacheKeyOverride !== null
            ? $this->cacheModel->cacheKeyForCustom($this->cacheKeyOverride, 'exists')
            : $this->cacheModel->cacheKeyFor($this, ['*'], 'exists');

        return $this->cacheModel->rememberInCache(
            $key,
            fn () => parent::exists(),
            $this->cacheTtlOverride,
            $this->cacheTtlOverridden
        );
    }

    // ---------------------------------------------------------------------
    // Writes — every mutation flushes the owning model's cache
    // ---------------------------------------------------------------------

    // Inserts add rows but never invalidate an existing find() cache (null
    // finds are not cached), so they only flush query/list caches.

    public function insert(array $values)
    {
        return tap(parent::insert($values), fn () => $this->flushOnWrite('queries'));
    }

    public function insertOrIgnore(array $values)
    {
        return tap(parent::insertOrIgnore($values), fn () => $this->flushOnWrite('queries'));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return tap(parent::insertGetId($values, $sequence), fn () => $this->flushOnWrite('queries'));
    }

    public function insertUsing(array $columns, $query)
    {
        return tap(parent::insertUsing($columns, $query), fn () => $this->flushOnWrite('queries'));
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        return tap(parent::insertOrIgnoreUsing($columns, $query), fn () => $this->flushOnWrite('queries'));
    }

    // Upsert may update unknown existing rows, so it flushes everything.

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        return tap(parent::upsert($values, $uniqueBy, $update), fn () => $this->flushOnWrite('full'));
    }

    // Update/delete may target a single row (surgical) or many (full flush).

    public function update(array $values)
    {
        return tap(parent::update($values), fn () => $this->flushOnWrite('auto'));
    }

    // updateFrom joins against other tables, so the touched rows are unknown.

    public function updateFrom(array $values)
    {
        return tap(parent::updateFrom($values), fn () => $this->flushOnWrite('full'));
    }

    public function delete($id = null)
    {
        return tap(parent::delete($id), fn () => $this->flushOnWrite('auto'));
    }

    public function truncate()
    {
        parent::truncate();
        $this->flushOnWrite('full');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        return tap(parent::increment($column, $amount, $extra), fn () => $this->flushOnWrite('auto'));
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        return tap(parent::decrement($column, $amount, $extra), fn () => $this->flushOnWrite('auto'));
    }

    /**
     * Flush the owning model's cache after a write, transaction-aware.
     *
     * @param  'queries'|'auto'|'full'  $scope
     */
    protected function flushOnWrite(string $scope): void
    {
        $model = $this->cacheModel;

        if ($model === null) {
            return;
        }

        if ($scope === 'queries') {
            $model->runFlush(fn () => $model->flushQueriesOnly());

            return;
        }

        if ($scope === 'auto') {
            $id = $this->singleWriteId();

            if ($id !== null && $model->rowCacheEnabled() && ! $model->cacheUsesTags()) {
                $model->runFlush(fn () => $model->flushForSingleRow($id));

                return;
            }
        }

        $model->runFlush(fn () => $model->flushCache());
    }

    /**
     * The primary-key value when this write can only touch that one row — i.e.
     * an `id = <scalar>` constraint AND-ed with any number of narrowing
     * conditions (a soft-delete `deleted_at is null`, extra column filters,
     * etc.). Any OR makes the target set unbounded, so we bail to a full flush.
     */
    protected function singleWriteId(): mixed
    {
        if ($this->cacheModel === null || ! $this->cacheModel->rowCacheEnabled()) {
            return null;
        }

        // A join can touch rows whose key never appears in the wheres (and an
        // `id = ?` there may belong to the joined table), so never go surgical.
        if (! empty($this->joins)) {
            return null;
        }

        $wheres = $this->wheres;

        if ($wheres === []) {
            return null;
        }

        foreach ($wheres as $where) {
            if (($where['boolean'] ?? 'and') !== 'and') {
                return null;
            }
        }

        foreach ($wheres as $where) {
            if (($where['type'] ?? null) === 'Basic'
                && ($where['operator'] ?? null) === '='
                && array_key_exists('value', $where)
                && is_scalar($where['value'])
                && $this->columnIsKey($where['column'] ?? '')) {
                return $where['value'];
            }
        }

        return null;
    }

    protected function columnIsKey(string $column): bool
    {
        $parts = explode('.', $column);

        if (count($parts) === 1) {
            return $parts[0] === $this->cacheModel->getKeyName();
        }

        // Qualified columns must belong to the model's own table; anything
        // else (another table, an alias we can't verify) is not "the key".
        return count($parts) === 2
            && $parts[0] === $this->cacheModel->getTable()
            && $parts[1] === $this->cacheModel->getKeyName();
    }
}
