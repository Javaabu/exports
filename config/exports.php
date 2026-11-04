<?php

use Javaabu\Exports\Http\Controllers\ExportsController;
use Javaabu\Exports\Jobs\ProcessExport;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Notifications\ExportCompletedNotification;
use Javaabu\Exports\Notifications\ExportFailedNotification;
use Javaabu\Exports\Policies\ExportPolicy;

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

    /*
    |--------------------------------------------------------------------------
    | The exports table and model
    |--------------------------------------------------------------------------
    |
    | user_key_type is the key type of the models that start exports (any
    | model, through the `user` morph): integer, uuid, ulid or string. Set it
    | before running the migration.
    |
    */

    'table' => 'exports',

    'user_key_type' => 'integer',

    'models' => [
        'export' => Export::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued exports
    |--------------------------------------------------------------------------
    |
    | connection and queue null mean the app's defaults. Progress is saved
    | (and broadcast) every progress_every rows; null means chunk_size.
    | The job can be replaced with a subclass of ProcessExport.
    |
    */

    'queue' => [
        'connection' => env('EXPORTS_QUEUE_CONNECTION'),
        'queue' => env('EXPORTS_QUEUE'),
        'timeout' => 3600,
        'progress_every' => null,
        'job' => ProcessExport::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | Files can be downloaded for expire_after_days, then `exports:prune`
    | deletes them. Signed links in notifications last signed_url_minutes,
    | or until the file expires if that's sooner.
    |
    */

    'expire_after_days' => 7,

    'signed_url_minutes' => 1440,

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The user who started a queued export is notified when it completes or
    | fails, if their model uses Notifiable. Set a notification to null to
    | turn it off.
    |
    */

    'notifications' => [
        'enabled' => true,
        'channels' => ['mail'],
        'completed' => ExportCompletedNotification::class,
        'failed' => ExportFailedNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The name of the signed download route used in notifications. Route::exports()
    | registers it as {name}.signed-download; change this if you change the name.
    |
    */

    'routes' => [
        'signed_download' => 'exports.signed-download',
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | The pages the exports routes render: blade (javaabu/forms components) or
    | inertia (React pages shipped through javaabu/manifest). Requests that
    | expect JSON always get JSON.
    |
    */

    'views' => env('EXPORTS_VIEWS', 'blade'),

    /*
    |--------------------------------------------------------------------------
    | Controllers and authorization
    |--------------------------------------------------------------------------
    |
    | Replace the controller with a subclass, or pass `controller` to
    | Route::exports(). The policy here is the package default; apps can also
    | override it with config('permissions.policies') or Gate::policy().
    | Turn off register_permissions to register your own.
    |
    */

    'controllers' => [
        'exports' => ExportsController::class,
    ],

    'policy' => ExportPolicy::class,

    'register_permissions' => true,

];
