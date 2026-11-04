<?php

namespace Javaabu\Exports\Contracts;

/**
 * A model that names its attributes for exports
 */
interface HasExportHeadings
{
    /**
     * Headings by attribute or column name, e.g. `['nid' => __('National ID')]`
     *
     * @return array<string, string>
     */
    public function exportHeadings(): array;
}
