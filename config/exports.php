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
    | Default columns
    |--------------------------------------------------------------------------
    |
    | When an export doesn't list its columns, it includes the model's table
    | columns except hidden attributes and these names (patterns allowed).
    | Timestamps (created_at, updated_at, deleted_at) are included unless
    | timestamps is false. With resolve_foreign_keys, a foreign key such as
    | role_id is replaced by its BelongsTo relation (role) when the model
    | defines one, and the related model is shown by its name.
    |
    */

    'exclude' => [
        'password',
        'remember_token',
        '*_token',
        '*_secret',
        'two_factor_recovery_codes',
        'api_key',
    ],

    'timestamps' => true,

    'resolve_foreign_keys' => true,

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    |
    | Dates are converted to this timezone (null means the app timezone) and
    | written with datetime_format, or date_format for attributes cast to
    | date. Lists (to-many relations) are joined with list_separator.
    |
    */

    'timezone' => null,

    'datetime_format' => 'Y-m-d H:i:s',

    'date_format' => 'Y-m-d',

    'list_separator' => ', ',

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
