<?php

namespace Javaabu\Exports\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * An Eloquent query that can go on the queue
 *
 * Builders hold a database connection and can't be serialized. This keeps the model,
 * connection, SQL, bindings, order and eager loads (constraint closures included), and
 * rebuilds the query as a subquery of the same name:
 * `select * from (select … from users where …) as users`.
 */
class SerializableQuery
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int, mixed>  $bindings
     * @param  array<int, array<string, mixed>>  $orders
     * @param  array<int, mixed>  $order_bindings
     * @param  array<string, SerializableClosure>  $eager_loads
     */
    public function __construct(
        public string $model,
        public ?string $connection,
        public string $sql,
        public array $bindings,
        public array $orders,
        public array $order_bindings,
        public array $eager_loads,
    ) {}

    public static function from(Builder|Relation $query): self
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;
        $model = $builder->getModel();
        $base = $builder->toBase();

        $eager_loads = array_map(
            fn (Closure $constraint) => new SerializableClosure($constraint),
            $builder->getEagerLoads(),
        );

        return new self(
            $model::class,
            $model->getConnectionName(),
            $base->toSql(),
            $base->getBindings(),
            $base->orders ?? [],
            $base->getRawBindings()['order'] ?? [],
            $eager_loads,
        );
    }

    public function toBuilder(): Builder
    {
        /** @var Model $model */
        $model = new $this->model;
        $model->setConnection($this->connection);

        $subquery = DB::connection($this->connection)->query()->fromRaw("({$this->sql}) as {$this->wrappedTable($model)}", $this->bindings);

        $query = $model->newQueryWithoutScopes()->setQuery($subquery);

        $base = $query->getQuery();
        $base->orders = $this->orders ?: null;
        $base->setBindings($this->order_bindings, 'order');

        $query->setEagerLoads(array_map(
            fn (SerializableClosure $constraint) => $constraint->getClosure(),
            $this->eager_loads,
        ));

        return $query;
    }

    protected function wrappedTable(Model $model): string
    {
        return $model->getConnection()->getQueryGrammar()->wrapTable($model->getTable());
    }
}
