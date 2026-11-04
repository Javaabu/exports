<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default format
    |--------------------------------------------------------------------------
    |
    | The format used when an export isn't given one and the file name has no
    | extension: xlsx, csv or ods.
    |
    */

    'format' => 'xlsx',

    /*
    |--------------------------------------------------------------------------
    | Enabled formats
    |--------------------------------------------------------------------------
    |
    | The formats users may choose from in the exports UI and API.
    |
    */

    'formats' => ['xlsx', 'csv', 'ods'],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Where stored and queued exports are written. `disk` null means the app's
    | default filesystem disk. Files are written locally to the temporary
    | directory first (null means the system temp directory), then streamed
    | to the disk.
    |
    */

    'disk' => env('EXPORTS_DISK'),

    'directory' => 'exports',

    'temporary_directory' => null,

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    |
    | How many models are loaded from the database at a time. Exports read with
    | lazyById() (or lazy() when the query has its own order), so memory depends
    | on this number, not on how many rows there are.
    |
    */

    'chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    */

    'datetime_format' => 'Y-m-d H:i:s',

    'date_format' => 'Y-m-d',

    /*
    |--------------------------------------------------------------------------
    | Right-to-left sheets
    |--------------------------------------------------------------------------
    |
    | XLSX sheets are right-to-left when this is true, or when it's 'auto' and
    | the app locale is one of the rtl_locales.
    |
    */

    'rtl' => 'auto',

    'rtl_locales' => ['dv', 'ar', 'fa', 'he', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | CSV
    |--------------------------------------------------------------------------
    |
    | The BOM lets Excel open UTF-8 (including Thaana) correctly. Values that a
    | spreadsheet would run as a formula (starting with =, +, -, @) are
    | prefixed with an apostrophe unless escape_formulas is false.
    |
    */

    'csv' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'bom' => true,
        'escape_formulas' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | XLSX
    |--------------------------------------------------------------------------
    */

    'xlsx' => [
        'auto_size' => true,
        'max_column_width' => 60,
    ],

];
