---
title: Configuration
sidebar_position: 10
---

Publish the config file to change any option:

```bash
php artisan vendor:publish --tag=exports-config
```

Everything has a default; most apps change nothing.

# Files

| Key | Default | |
| --- | --- | --- |
| `format` | `'xlsx'` | Format used when none is given and the file name has no extension |
| `formats` | `['xlsx', 'csv', 'ods']` | Formats users may choose on the exports page and API |
| `disk` | `env('EXPORTS_DISK')` | Disk for stored and queued exports; null means the default disk |
| `directory` | `'exports'` | Directory on the disk |
| `temporary_directory` | `null` | Where files are written before they're sent or moved; null means the system temp directory |
| `csv.delimiter` | `','` | |
| `csv.enclosure` | `'"'` | |
| `csv.bom` | `true` | Adds a UTF-8 BOM so Excel reads Thaana |
| `csv.escape_formulas` | `true` | Prefixes values starting with `=`, `+`, `-`, `@` with an apostrophe |
| `xlsx.auto_size` | `true` | Sizes columns to their widest value |
| `xlsx.max_column_width` | `60` | |
| `rtl` | `'auto'` | Right-to-left XLSX sheets: `true`, `false`, or `'auto'` for the `rtl_locales` |
| `rtl_locales` | `['dv', 'ar', 'fa', 'he', 'ur']` | |

# Reading and formatting

| Key | Default | |
| --- | --- | --- |
| `chunk_size` | `1000` | Models loaded at a time. See [Performance](performance.md). |
| `exclude` | `['password', 'remember_token', '*_token', '*_secret', 'two_factor_recovery_codes', 'api_key']` | Columns left out of the default columns (`Str::is()` patterns) |
| `timestamps` | `true` | Include `created_at`, `updated_at` and `deleted_at` in the default columns |
| `resolve_foreign_keys` | `true` | Replace `role_id` with the `role` relation when the model has it |
| `timezone` | `null` | Timezone dates are converted to; null means `app.timezone` |
| `datetime_format` | `'Y-m-d H:i:s'` | |
| `date_format` | `'Y-m-d'` | For attributes cast to `date` |
| `list_separator` | `', '` | Joins values of to-many relations |

# Queued exports

| Key | Default | |
| --- | --- | --- |
| `table` | `'exports'` | |
| `user_key_type` | `'integer'` | Key type of user models: `integer`, `uuid`, `ulid` or `string`. Set before migrating. |
| `models.export` | `Export::class` | Your own subclass of `Javaabu\Exports\Models\Export` |
| `queue.connection` | `env('EXPORTS_QUEUE_CONNECTION')` | Null means the default connection |
| `queue.queue` | `env('EXPORTS_QUEUE')` | Null means the default queue |
| `queue.timeout` | `3600` | Job timeout in seconds |
| `queue.progress_every` | `null` | Save and broadcast progress every this many rows; null means `chunk_size` |
| `queue.job` | `ProcessExport::class` | Your own subclass of the job |
| `expire_after_days` | `7` | Days a file can be downloaded before `exports:prune` deletes it |
| `signed_url_minutes` | `1440` | Lifetime of signed links in notifications (capped at the file's expiry) |
| `notifications.enabled` | `true` | |
| `notifications.channels` | `['mail']` | |
| `notifications.completed` | `ExportCompletedNotification::class` | Null to turn it off |
| `notifications.failed` | `ExportFailedNotification::class` | Null to turn it off |
| `routes.signed_download` | `'exports.signed-download'` | Name of the signed download route used in notifications |

# Live progress

| Key | Default | |
| --- | --- | --- |
| `broadcasting.enabled` | `env('EXPORTS_BROADCASTING', 'auto')` | `true`, `false`, or `'auto'` (when laravel/reverb is installed and broadcasting is on) |
| `broadcasting.channel` | `'exports'` | Channel name prefix |
| `broadcasting.guards` | `null` | Guards that authorize the channels |
| `broadcasting.poll_interval` | `3000` | Milliseconds between polls when not broadcasting |

# Pages and authorization

| Key | Default | |
| --- | --- | --- |
| `views` | `env('EXPORTS_VIEWS', 'blade')` | `blade` or `inertia` |
| `controllers.exports` | `ExportsController::class` | |
| `policy` | `ExportPolicy::class` | The default policy the package registers |
| `register_permissions` | `true` | Register `view_exports` and the rest with javaabu/permissions |
