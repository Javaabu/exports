<?php
/**
 * Base Model Export class
 */

namespace Javaabu\Exports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

abstract class ModelExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @var Builder
     */
    protected $query;

    /**
     * Create a new logs export instance.
     */
    public function __construct($query = null)
    {
        $this->query = $query;
    }

    /**
     * Get the model class
     */
    public abstract function modelClass(): string;

    public function allowedAttributes(): array
    {
        $model_class = $this->modelClass();

        /** @var Model $empty_model */
        $empty_model = (new $model_class());

        $attributes = array_values(array_diff(\Schema::getColumnListing($empty_model->getTable()), $empty_model->getHidden()));

        return array_merge($attributes, $this->relationsToInclude());
    }

    /**
     * @return Builder
     */
    public function query()
    {
        if ($this->query) {
            return $this->query;
        }

        $model_class = $this->modelClass();

        $query = $model_class::query();

        if ($relations = $this->relationsToInclude()) {
            $query->with($relations);
        }

        return $query;
    }

    public function relationsToInclude(): array
    {
        return [];
    }

    /**
     * @param  Model  $model
     */
    public function map($model): array
    {
        $attributes = $model->only($this->allowedAttributes());

        foreach ($attributes as $attribute => $value) {
            $attributes[$attribute] = $this->formatValue($attribute, $value);
        }

        return array_values($attributes);
    }

    public function isAdminModel(string $attribute, mixed $value): bool
    {
        return $value instanceof Model && method_exists($value, 'getAdminLinkNameAttribute');
    }

    public function isAdminModelCollection(string $attribute, mixed $value): bool
    {
        if (! $value instanceof Collection) {
            return false;
        }

        foreach ($value as $item) {
            if (! $this->isAdminModel($attribute, $item)) {
                return false;
            }
        }

        return true;
    }

    public function formatValue(string $attribute, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->getEnumLabel();
        } elseif ($this->isAdminModelCollection($attribute, $value)) {
            return $value->implode('admin_link_name', ',');
        } elseif ($this->isAdminModel($attribute, $value)) {
            return $value->admin_link_name;
        } elseif (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        return $value;
    }

    public function headings(): array
    {
        return array_map(function ($slug) {
            return Str::of($slug)
                ->camel()
                ->snake()
                ->replace('_', ' ')
                ->title()
                ->toString();
        }, $this->allowedAttributes());
    }
}
