<?php
/**
 * Base Model Export class
 */

namespace Javaabu\Exports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
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

    /**
     * Get the allowed attributes
     */
    public function allowedAttributes(): array
    {
        $model_class = $this->modelClass();

        /** @var Model $empty_model */
        $empty_model = (new $model_class());

        return array_values(array_diff(\Schema::getColumnListing($empty_model->getTable()), $empty_model->getHidden()));
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

        return $model_class::query();
    }

    /**
     * @param  Model  $model
     */
    public function map($model): array
    {
        return array_values($model->only($this->allowedAttributes()));
    }

    public function headings(): array
    {
        return array_map(function ($slug) {
            return Str::title(str_replace('_', ' ', $slug));
        }, $this->allowedAttributes());
    }
}
