---
title: Upgrading from v1
sidebar_position: 1.25
---

v2 replaces maatwebsite/excel with OpenSpout, streams every export, picks smarter defaults, and adds queued exports with an exports page. Most `ModelExport` classes keep working unchanged; their files look a little different.

# Requirements

PHP 8.4 or 8.5, and Laravel 12 or 13.

```bash
composer require javaabu/exports:^2.0
php artisan migrate
php artisan permissions:sync
```

v2 requires [javaabu/permissions](https://github.com/Javaabu/permissions) v3, for its policy and permissions.

# maatwebsite/excel is no longer used

**Why:** maatwebsite/excel builds sheets with PhpSpreadsheet, which keeps the whole workbook in memory and, for queued exports, re-opens the file for every chunk. Exports with hundreds of thousands of rows run out of memory or take very long. PhpSpreadsheet 1.x also can't be installed on PHP 8.5. v2 writes with [OpenSpout](https://github.com/openspout/openspout), which streams rows to disk: a million rows take about 4 MB of memory (see [Performance](advanced-usage/performance.md)).

**What it means for you:**

- `ModelExport` no longer implements maatwebsite's `FromQuery`, `WithHeadings`, `WithMapping` and `ShouldAutoSize`, and doesn't use its `Exportable` trait. Its own `download()`, `store()` and `queue()` replace them.
- `Excel::download(new UsersExport, 'users.xlsx')` becomes `UsersExport::make()->download('users.xlsx')`.
- `Excel::store(new UsersExport, 'users.xlsx', 's3')` becomes `UsersExport::make()->store('users.xlsx', 's3')`.
- maatwebsite's `->queue('users.xlsx')` becomes `->queue($user, filename: 'users')`, which returns an `Export` record. See [Queued exports](basic-usage/queued-exports.md).
- maatwebsite concerns such as `WithStyles`, `WithColumnFormatting`, `WithEvents`, `WithDrawings` and `WithMultipleSheets` aren't supported. If you rely on them for some exports, require maatwebsite/excel yourself (on PHP 8.4) and keep those classes on it; they don't need this package.
- Composer no longer installs maatwebsite/excel or phpoffice/phpspreadsheet for you. If other code uses them, require them directly.

# Changes to ModelExport

## Still works

`modelClass()`, `relationsToInclude()`, `allowedAttributes()`, `query()`, `formatValue()`, `getEnumLabel()`, `isAdminModel()`, `isAdminModelCollection()`, `headings()` and `map()` can still be overridden, and the constructor still takes a query.

## Different defaults

| v1 | v2 | To keep v1 |
| --- | --- | --- |
| Every non-hidden column | Also leaves out `*_token`, `*_secret`, `remember_token`, `two_factor_recovery_codes`, `api_key` | Set `exports.exclude` to `[]` |
| `role_id` and `role` both included | `role_id` is left out when `relationsToInclude()` has its `BelongsTo`, and replaced by `role` when the model has a `role()` relation | Set `exports.resolve_foreign_keys` to `false`, or override `allowedAttributes()` |
| Headings `Id`, `Role Id`, `User Role` | `ID`, `Role ID`, `User Role`; from validation attribute names and the model when they exist; translated | Override `headings()` |
| Booleans `True` / `False` | `Yes` / `No`, translated | Override `formatValue()` |
| Enums: `getLabel()` or the case name | `getLabel()`, `label()`, or a headline of the value (`pending_review` → `Pending Review`) | Override `getEnumLabel()` |
| Dates as Carbon objects | Strings in the app timezone, `Y-m-d H:i:s` (`Y-m-d` for `date` casts) | Set `exports.datetime_format` |
| Related models joined with `,` | Joined with `, ` | Set `exports.list_separator` to `','` |
| Related models without `admin_link_name` serialized | Shown by `name`, `title`, `label` or their key | Override `displayName()` |
| XLSX columns auto-sized by PhpSpreadsheet | Sized from the widest value | — |

## Type hints

`map(Model $model)` and `formatValue(Model $model, …)` are typed now. Overrides without types (`map($model)`) still work, because PHP allows a child to widen a parameter.

## New

- `columns()` with dot notation and `Column` definitions. See [Model exports](basic-usage/model-exports.md#choosing-columns).
- `label()`, `filename()`, `authorize()`, `fromRequest()`, `chunkSize()`, `totalRows()`.
- `IterableExport` for rows that aren't models.

# A typical v1 export, in v2

```php
// v1
class UsersExport extends ModelExport
{
    public function modelClass(): string
    {
        return User::class;
    }

    public function relationsToInclude(): array
    {
        return ['roles'];
    }
}

return Excel::download(new UsersExport($query), 'users.xlsx');
```

```php
// v2: the class is unchanged
return UsersExport::make($query)->download('users.xlsx');
```

Or, for big tables:

```php
UsersExport::make($query)->queue($request->user(), 'xlsx');

return redirect()->route('exports.index');
```

# Optional: the exports page

Register the routes and your exports to give users a page to start, follow and download exports. See [Exports page and API](basic-usage/exports-page-and-api.md).
