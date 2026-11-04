<?php

namespace Javaabu\Exports;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Javaabu\Exports\Columns\Column;
use Javaabu\Exports\Concerns\WritesFiles;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Contracts\HasExportHeadings;
use JsonSerializable;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use UnitEnum;

/**
 * Exports the models of a query, one row per model
 *
 * Without configuration it includes the model's table columns, minus hidden attributes,
 * secrets and foreign keys it can replace with their relation, with readable headings and
 * formatted values. Override columns() to choose the columns yourself.
 */
abstract class ModelExport implements Exportable
{
    use WritesFiles;

    /** Attributes tried, in order, to show a related model */
    protected const array DISPLAY_ATTRIBUTES = ['admin_link_name', 'name', 'title', 'label'];

    /** @var array<int, Column>|null */
    protected ?array $resolved_columns = null;

    /** @var array<string, bool> */
    protected array $relation_names = [];

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
     * The columns, in order: attribute names, relation names, dot notation
     * (`userRole.name`) or Column instances
     *
     * @return array<int, string|Column>
     */
    public function columns(): array
    {
        return $this->allowedAttributes();
    }

    /**
     * The default columns: table columns that aren't hidden or excluded, foreign keys
     * replaced by their relation, then relationsToInclude()
     *
     * @return array<int, string>
     */
    public function allowedAttributes(): array
    {
        $model = $this->newModel();
        $included_relations = $this->relationsToInclude();
        $excluded = $this->excludedColumns($model, $included_relations);

        $columns = [];

        foreach ($this->tableColumns($model) as $column) {
            if ($this->isExcluded($column, $excluded)) {
                continue;
            }

            $columns[] = $this->foreignKeyRelation($model, $column, $included_relations) ?? $column;
        }

        return array_values(array_unique(array_merge($columns, $included_relations)));
    }

    /**
     * Relations added as columns after the table columns, shown by their display name
     *
     * @return array<int, string>
     */
    public function relationsToInclude(): array
    {
        return [];
    }

    public function query(): Builder|Relation
    {
        $query = $this->query ?? $this->modelClass()::query();

        if ($relations = $this->eagerLoads()) {
            $query->with($relations);
        }

        return $query;
    }

    /**
     * The relations the columns need, eager loaded for every chunk
     *
     * @return array<int, string>
     */
    public function eagerLoads(): array
    {
        $model = $this->newModel();
        $relations = $this->relationsToInclude();

        foreach ($this->resolvedColumns() as $column) {
            if ($column->getValueResolver()) {
                continue;
            }

            if ($path = $column->relationPath()) {
                $relations[] = $path;

                continue;
            }

            if ($this->isRelation($model, $column->name)) {
                $relations[] = $column->name;
            }
        }

        return array_values(array_unique($relations));
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
     * The models, loaded a chunk at a time: by key with lazyById(), or with lazy()
     * when the query has its own order
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
        $row = [];

        foreach ($this->resolvedColumns() as $column) {
            $row[] = $this->columnValue($model, $column);
        }

        return $row;
    }

    /**
     * Format a value for the file. Override it for one attribute and call the parent for the rest.
     */
    public function formatValue(Model $model, string $attribute, mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $this->getEnumLabel($model, $attribute, $value);
        }

        if ($this->isAdminModelCollection($model, $attribute, $value)) {
            return $value->map(fn (Model $item) => $this->displayName($item))->implode($this->listSeparator());
        }

        if ($value instanceof Model) {
            return $this->displayName($value);
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item) => $this->formatValue($model, $attribute, $item))
                ->filter(fn (mixed $item) => $item !== null && $item !== '')
                ->implode($this->listSeparator());
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        if ($value instanceof DateTimeInterface) {
            return $this->formatDate($model, $attribute, $value);
        }

        if (is_array($value) || $value instanceof JsonSerializable) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    public function getEnumLabel(Model $model, string $attribute, UnitEnum $value): string
    {
        foreach (['getLabel', 'label'] as $method) {
            if (method_exists($value, $method)) {
                return (string) $value->{$method}();
            }
        }

        $name = $value instanceof BackedEnum && is_string($value->value)
            ? $value->value
            : $value->name;

        return __(Str::of($name)->lower()->headline()->toString());
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

        if ($value->isEmpty()) {
            return false;
        }

        foreach ($value as $item) {
            if (! $item instanceof Model) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $model = $this->newModel();

        $model_headings = $model instanceof HasExportHeadings
            ? $model->exportHeadings()
            : [];

        return array_map(
            fn (Column $column) => $this->heading($column, $model_headings),
            $this->resolvedColumns(),
        );
    }

    /**
     * The columns as Column instances
     *
     * @return array<int, Column>
     */
    public function resolvedColumns(): array
    {
        return $this->resolved_columns ??= array_map(
            fn (string|Column $column) => is_string($column) ? Column::make($column) : $column,
            array_values($this->columns()),
        );
    }

    /**
     * The name that shows a related model: its admin_link_name, name, title or label, or its key
     */
    public function displayName(Model $model): mixed
    {
        foreach (static::DISPLAY_ATTRIBUTES as $attribute) {
            $value = $attribute === 'admin_link_name'
                ? (method_exists($model, 'getAdminLinkNameAttribute') ? $model->admin_link_name : null)
                : $model->getAttribute($attribute);

            if ($value === null || $value === '') {
                continue;
            }

            return $value instanceof UnitEnum
                ? $this->getEnumLabel($model, $attribute, $value)
                : $value;
        }

        return $model->getKey();
    }

    protected function columnValue(Model $model, Column $column): mixed
    {
        $value = $column->getValueResolver()
            ? ($column->getValueResolver())($model)
            : $this->rawValue($model, $column->name);

        if ($formatter = $column->getFormatter()) {
            return $formatter($value, $model);
        }

        return $this->formatValue($model, $column->name, $value);
    }

    /**
     * The value of an attribute, a relation or a dot-notation path. Paths through
     * to-many relations give a collection of values.
     */
    protected function rawValue(Model $model, string $path): mixed
    {
        $value = $model;

        foreach (explode('.', $path) as $segment) {
            $value = $this->segmentValue($value, $segment);

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    protected function segmentValue(mixed $value, string $segment): mixed
    {
        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item) => $this->segmentValue($item, $segment))
                ->flatten(1)
                ->filter(fn (mixed $item) => $item !== null)
                ->values();
        }

        if ($value instanceof Model) {
            return $value->getAttribute($segment);
        }

        return data_get($value, $segment);
    }

    /**
     * @param  array<string, string>  $model_headings
     */
    protected function heading(Column $column, array $model_headings): string
    {
        if (($label = $column->getLabel()) !== null) {
            return $label;
        }

        if (isset($model_headings[$column->name])) {
            return $model_headings[$column->name];
        }

        $key = 'validation.attributes.' . Str::snake(str_replace('.', '_', $column->name));

        if (trans()->has($key)) {
            return (string) __($key);
        }

        return __($this->headline($column));
    }

    /**
     * `Email`, `Role ID` for `role_id`, `User Role` for `userRole.name`, `User Role Email` for `userRole.email`
     */
    protected function headline(Column $column): string
    {
        $path = $column->relationPath();
        $attribute = $column->attribute();

        $name = $path && in_array($attribute, static::DISPLAY_ATTRIBUTES, true)
            ? $path
            : $column->name;

        $headline = Str::headline(str_replace('.', '_', $name));

        return preg_replace('/\bId$/', 'ID', $headline);
    }

    protected function formatDate(Model $model, string $attribute, DateTimeInterface $value): string
    {
        $date = $value instanceof CarbonInterface
            ? $value->copy()
            : Carbon::instance($value);

        $date->setTimezone((string) (config('exports.timezone') ?: config('app.timezone', 'UTC')));

        $cast = $model->getCasts()[$attribute] ?? null;

        $format = in_array(Str::before((string) $cast, ':'), ['date', 'immutable_date'], true)
            ? config('exports.date_format', 'Y-m-d')
            : config('exports.datetime_format', 'Y-m-d H:i:s');

        return $date->format((string) $format);
    }

    protected function listSeparator(): string
    {
        return (string) config('exports.list_separator', ', ');
    }

    /**
     * @return array<int, string>
     */
    protected function tableColumns(Model $model): array
    {
        return Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
    }

    /**
     * Column names and patterns left out of the default columns
     *
     * @param  array<int, string>  $included_relations
     * @return array<int, string>
     */
    protected function excludedColumns(Model $model, array $included_relations): array
    {
        $excluded = array_merge($model->getHidden(), config('exports.exclude', []));

        if (! config('exports.timestamps', true)) {
            $excluded = array_merge($excluded, array_filter([
                $model->getCreatedAtColumn(),
                $model->getUpdatedAtColumn(),
                method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
            ]));
        }

        foreach ($included_relations as $relation_name) {
            $relation = $this->relation($model, $relation_name);

            if ($relation instanceof MorphTo) {
                $excluded[] = $relation->getForeignKeyName();
                $excluded[] = $relation->getMorphType();

                continue;
            }

            if ($relation instanceof BelongsTo) {
                $excluded[] = $relation->getForeignKeyName();
            }
        }

        return $excluded;
    }

    /**
     * @param  array<int, string>  $excluded
     */
    protected function isExcluded(string $column, array $excluded): bool
    {
        foreach ($excluded as $pattern) {
            if (Str::is($pattern, $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The BelongsTo relation that replaces a foreign key column, `role` for `role_id`,
     * when `exports.resolve_foreign_keys` is on and the model defines it
     *
     * @param  array<int, string>  $included_relations
     */
    protected function foreignKeyRelation(Model $model, string $column, array $included_relations): ?string
    {
        if (! config('exports.resolve_foreign_keys', true)) {
            return null;
        }

        if (! str_ends_with($column, '_id')) {
            return null;
        }

        $relation_name = Str::camel(Str::beforeLast($column, '_id'));

        if (in_array($relation_name, $included_relations, true)) {
            return null;
        }

        $relation = $this->relation($model, $relation_name);

        if (! $relation instanceof BelongsTo || $relation instanceof MorphTo) {
            return null;
        }

        if ($relation->getForeignKeyName() !== $column) {
            return null;
        }

        return $relation_name;
    }

    protected function isRelation(Model $model, string $name): bool
    {
        return $this->relation_names[$name] ??= $this->relation($model, $name) !== null;
    }

    /**
     * The relation a model method returns. Only public, non-static methods without required
     * parameters, declared by the app's model rather than by Laravel, are called, and only when
     * their return type is a relation or isn't declared.
     */
    protected function relation(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        $method = new ReflectionMethod($model, $name);

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        if (str_starts_with($method->getDeclaringClass()->getName(), 'Illuminate\\')) {
            return null;
        }

        $return_type = $method->getReturnType();

        if ($return_type instanceof ReflectionNamedType && ! is_a($return_type->getName(), Relation::class, true)) {
            return null;
        }

        try {
            $relation = $model->{$name}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }

    protected function newModel(): Model
    {
        $model_class = $this->modelClass();

        return new $model_class;
    }
}
