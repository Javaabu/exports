<?php

namespace Javaabu\Exports\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A column of a model export
 *
 * ```php
 * Column::make('userRole.name')->label(__('Role'));
 * Column::make('created_at')->format(fn (Carbon $value) => $value->toDateString());
 * Column::make('full_address')->value(fn (User $user) => "{$user->island}, {$user->atoll}");
 * ```
 */
class Column
{
    protected string|Closure|null $label = null;

    /** @var (Closure(mixed, Model): mixed)|null */
    protected ?Closure $format = null;

    /** @var (Closure(Model): mixed)|null */
    protected ?Closure $value = null;

    /**
     * @param  string  $name  an attribute (`email`), a relation (`organizations`), or a
     *                        relation's attribute in dot notation (`userRole.name`)
     */
    public function __construct(
        public readonly string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * The heading of the column
     */
    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Format the column's value yourself, instead of the export's formatValue()
     *
     * @param  Closure(mixed, Model): mixed  $callback  receives the raw value and the model
     */
    public function format(Closure $callback): static
    {
        $this->format = $callback;

        return $this;
    }

    /**
     * Compute the column's value from the model instead of reading an attribute
     *
     * @param  Closure(Model): mixed  $callback
     */
    public function value(Closure $callback): static
    {
        $this->value = $callback;

        return $this;
    }

    public function getLabel(): ?string
    {
        if ($this->label instanceof Closure) {
            return (string) ($this->label)();
        }

        return $this->label;
    }

    /**
     * @return (Closure(mixed, Model): mixed)|null
     */
    public function getFormatter(): ?Closure
    {
        return $this->format;
    }

    /**
     * @return (Closure(Model): mixed)|null
     */
    public function getValueResolver(): ?Closure
    {
        return $this->value;
    }

    /**
     * The relation path of a dot-notation column: `userRole` for `userRole.name`
     */
    public function relationPath(): ?string
    {
        return str_contains($this->name, '.')
            ? Str::beforeLast($this->name, '.')
            : null;
    }

    /**
     * The last segment of the name: `name` for `userRole.name`
     */
    public function attribute(): string
    {
        return Str::afterLast($this->name, '.');
    }
}
