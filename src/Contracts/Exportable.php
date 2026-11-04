<?php

namespace Javaabu\Exports\Contracts;

/**
 * Anything the package can write to a file: headings, and rows of values streamed one at a time
 */
interface Exportable
{
    /**
     * The heading row
     *
     * @return array<int, string>
     */
    public function headings(): array;

    /**
     * The data rows. Yield them lazily so memory stays flat however many rows there are.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function rows(): iterable;

    /**
     * The number of rows, for progress, or null when unknown
     */
    public function totalRows(): ?int;

    /**
     * The file name without an extension, e.g. `users`
     */
    public function filename(): string;
}
