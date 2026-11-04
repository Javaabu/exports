<?php

namespace Javaabu\Exports;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Javaabu\Exports\Concerns\WritesFiles;
use Javaabu\Exports\Contracts\Exportable;

/**
 * Exports the models of a query, one row per model
 */
abstract class ModelExport implements Exportable
{
    use WritesFiles;

    public function __construct(
        protected Builder|Relation|null $query = null,
    ) {}

    /**
     * The model class
     *
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;

    /**
     * @return array<int, string>
     */
    public function allowedAttributes(): array
    {
        $model = $this->newModel();

        $attributes = array_values(array_diff(
            Schema::connection($model->getConnectionName())->getColumnListing($model->getTable()),
            $model->getHidden(),
        ));

        return array_merge($attributes, $this->relationsToInclude());
    }

    public function query(): Builder|Relation
    {
        if ($this->query) {
            return $this->query;
        }

        $query = $this->modelClass()::query();

        if ($relations = $this->relationsToInclude()) {
            $query->with($relations);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function relationsToInclude(): array
    {
        return [];
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(): iterable
    {
        foreach ($this->models() as $model) {
            yield $this->map($model);
        }
    }

    /**
     * The models, loaded a chunk at a time
     *
     * @return LazyCollection<int, Model>
     */
    public function models(): LazyCollection
    {
        $query = $this->query();
        $chunk_size = $this->chunkSize();

        $base_query = $query->toBase();

        if ($base_query->orders || $base_query->unionOrders) {
            return $query->lazy($chunk_size);
        }

        $model = $query->getModel();

        return $query->lazyById($chunk_size, $model->getQualifiedKeyName(), $model->getKeyName());
    }

    public function totalRows(): ?int
    {
        return $this->query()->toBase()->getCountForPagination();
    }

    public function chunkSize(): int
    {
        return max((int) config('exports.chunk_size', 1000), 1);
    }

    public function filename(): string
    {
        return Str::of(class_basename($this->modelClass()))->plural()->kebab()->toString();
    }

    /**
     * @return array<int, mixed>
     */
    public function map(Model $model): array
    {
        $attributes = $model->only($this->allowedAttributes());

        foreach ($attributes as $attribute => $value) {
            $attributes[$attribute] = $this->formatValue($model, $attribute, $value);
        }

        return array_values($attributes);
    }

    public function isAdminModel(Model $model, string $attribute, mixed $value): bool
    {
        return $value instanceof Model && method_exists($value, 'getAdminLinkNameAttribute');
    }

    public function isAdminModelCollection(Model $model, string $attribute, mixed $value): bool
    {
        if (! $value instanceof Collection) {
            return false;
        }

        foreach ($value as $item) {
            if (! $this->isAdminModel($model, $attribute, $item)) {
                return false;
            }
        }

        return true;
    }

    public function getEnumLabel(Model $model, string $attribute, mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return method_exists($value, 'getLabel') ? $value->getLabel() : $value->name;
        }

        return '';
    }

    public function formatValue(Model $model, string $attribute, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $this->getEnumLabel($model, $attribute, $value);
        }

        if ($this->isAdminModelCollection($model, $attribute, $value)) {
            return $value->implode('admin_link_name', ',');
        }

        if ($this->isAdminModel($model, $attribute, $value)) {
            return $value->admin_link_name;
        }

        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(function (string $slug) {
            return Str::of($slug)
                ->camel()
                ->snake()
                ->replace('_', ' ')
                ->title()
                ->toString();
        }, $this->allowedAttributes());
    }

    protected function newModel(): Model
    {
        $model_class = $this->modelClass();

        return new $model_class;
    }
}
