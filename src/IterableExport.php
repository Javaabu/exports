<?php

namespace Javaabu\Exports;

use Closure;
use Javaabu\Exports\Concerns\WritesFiles;
use Javaabu\Exports\Contracts\Exportable;

/**
 * Exports rows that aren't models: an array, a generator, a LazyCollection,
 * or a closure that returns one
 *
 * ```php
 * IterableExport::make(['Name', 'Island'], $rows, 'people')->download();
 * ```
 */
class IterableExport implements Exportable
{
    use WritesFiles;

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>|Closure(): iterable<int, array<int, mixed>>  $rows
     */
    public function __construct(
        protected array $headings,
        protected iterable|Closure $rows,
        protected string $filename = 'export',
        protected ?int $total_rows = null,
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function rows(): iterable
    {
        $rows = $this->rows instanceof Closure
            ? ($this->rows)()
            : $this->rows;

        foreach ($rows as $row) {
            yield array_values((array) $row);
        }
    }

    public function totalRows(): ?int
    {
        if ($this->total_rows !== null) {
            return $this->total_rows;
        }

        return is_countable($this->rows) ? count($this->rows) : null;
    }

    public function filename(): string
    {
        return $this->filename;
    }
}
