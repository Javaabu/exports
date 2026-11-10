---
title: Model exports
sidebar_position: 1
---

A model export turns the models of a query into rows: one row per model, one column per attribute or relation.

# Creating an export

Extend `ModelExport` and return the model class:

```php
namespace App\Exports;

use App\Models\User;
use Javaabu\Exports\ModelExport;

class UsersExport extends ModelExport
{
    public function modelClass(): string
    {
        return User::class;
    }
}
```

Use it with [download(), store() or queue()](downloading-and-storing.md):

```php
return UsersExport::make()->download('users.xlsx');
```

Pass a query to export some of the models. `make()` passes its arguments to the constructor:

```php
UsersExport::make(User::query()->where('is_active', true)->orderBy('name'))->download();

UsersExport::make($organization->users())->download(); // relations work too
```

# The default columns

Without any configuration, an export includes the model's table columns, in table order, except:

- the model's `$hidden` attributes
- the names in `exports.exclude`: `password`, `remember_token`, `*_token`, `*_secret`, `two_factor_recovery_codes`, `api_key` (patterns allowed)
- `created_at`, `updated_at` and `deleted_at` when `exports.timestamps` is `false`

A foreign key is replaced by its relation when the model defines a `BelongsTo` with the same name: `role_id` becomes `role`, and the column shows the role's name. Turn this off with `exports.resolve_foreign_keys`.

For a `users` table with `id`, `name`, `email`, `password`, `role_id`, `status`, `created_at` and `updated_at`, and a `role()` relation, the file looks like this:

| ID | Name | Email | Role | Status | Created At | Updated At |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | އަހުމަދު | ahmed@example.mv | Editor | Approved | 2026-11-04 09:30:00 | 2026-11-04 09:30:00 |

# Choosing columns

Override `columns()` to pick the columns and their order. A column is an attribute, a relation, or a relation's attribute in dot notation:

```php
use Javaabu\Exports\Columns\Column;

class UsersExport extends ModelExport
{
    public function modelClass(): string
    {
        return User::class;
    }

    public function columns(): array
    {
        return [
            'name',
            'email',
            'status',                      // an enum: its label
            'role',                        // a relation: the related model's name
            'role.description',            // a relation's attribute
            'organizations.name',          // a to-many relation: "Javaabu, Gov.mv"
            Column::make('national_id')->label(__('National ID')),
            Column::make('created_at')->format(fn ($value) => $value->toDateString()),
            Column::make('address')->value(fn (User $user) => "{$user->island}, {$user->atoll}"),
        ];
    }
}
```

Relations used by the columns are eager loaded for every chunk, so there are no N+1 queries. Relations already eager loaded on a query you pass in, with or without constraints, are kept.

## Column options

| Method | Does |
| --- | --- |
| `Column::make(string $name)` | A column for an attribute, relation or dot-notation path |
| `->label(string\|Closure $label)` | The heading |
| `->format(Closure $callback)` | Formats the value yourself: `fn (mixed $value, Model $model) => …`. The export's `formatValue()` isn't called. |
| `->value(Closure $callback)` | Computes the value from the model: `fn (Model $model) => …`. Nothing is eager loaded for it. |

## Adding relations to the default columns

To keep the default columns and add relations at the end, list them in `relationsToInclude()`. Their foreign keys are left out:

```php
public function relationsToInclude(): array
{
    return ['role', 'organizations'];
}
```

`allowedAttributes()` returns the default columns. Override it, or `columns()`, to change them.

# Headings

Each column's heading is the first of:

1. the column's `label()`
2. the model's `exportHeadings()`, if it implements `Javaabu\Exports\Contracts\HasExportHeadings`
3. the validation attribute name `validation.attributes.{column}` from `lang/{locale}/validation.php` (dots become underscores)
4. a headline of the name, translated: `role_id` → `Role ID`, `role.name` → `Role`, `role.description` → `Role Description`

```php
use Javaabu\Exports\Contracts\HasExportHeadings;

class User extends Authenticatable implements HasExportHeadings
{
    public function exportHeadings(): array
    {
        return [
            'nid' => __('National ID'),
            'role.name' => __('Role'),
        ];
    }
}
```

Because headlines go through `__()`, add them to `lang/dv.json` to get Dhivehi headings. The package translates `ID`, `Name`, `Email`, `Status`, `Created At`, `Updated At` and `Deleted At` already.

To set every heading at once, override `headings()` and return one heading per column.

# Values

`formatValue()` turns each value into something a spreadsheet shows well:

| Value | Written as |
| --- | --- |
| An enum | `getLabel()` or `label()` if the enum has one, otherwise a headline of its value: `pending_review` → `Pending Review` |
| A boolean | `Yes` or `No`, translated (`އާނ` / `ނޫން` in Dhivehi) |
| A date | In `exports.timezone` (the app timezone by default), as `exports.datetime_format`, or `exports.date_format` for attributes cast to `date` |
| An array or JSON cast | JSON, with Thaana unescaped |
| A related model | Its `admin_link_name`, `name`, `title` or `label`, or its key |
| A collection | Each item formatted, joined with `exports.list_separator` (`, `) |
| Anything else | As it is |

Override `formatValue()` for one attribute and call the parent for the rest:

```php
use Illuminate\Database\Eloquent\Model;

public function formatValue(Model $model, string $attribute, mixed $value): mixed
{
    if ($attribute === 'salary') {
        return number_format($value / 100, 2);
    }

    return parent::formatValue($model, $attribute, $value);
}
```

Override `getEnumLabel()` to change how every enum is shown, or `displayName()` for related models.

# The query

`query()` returns the query passed to `make()`, or `Model::query()`, with the columns' relations eager loaded. Override it to add scopes for every use of the export:

```php
public function query(): Builder|Relation
{
    return parent::query()->where('is_active', true);
}
```

Exports read with `lazyById()`, or `lazy()` when the query has an `orderBy()`. Keep the primary key in any `select()`. See [Performance](../advanced-usage/performance.md).

# Names

| Method | Default | Used for |
| --- | --- | --- |
| `filename()` | `users` for `User` | Download and stored file names: `users-2026-11-04-093015.xlsx` |
| `label()` | `Users`, translated | The exports page and notifications |

# Exports that aren't models

`IterableExport` writes any rows: an array, a generator, a `LazyCollection`, or a closure that returns one.

```php
use Javaabu\Exports\IterableExport;

return IterableExport::make(
    ['ރަށް', 'އަތޮޅު', 'އާބާދީ'],
    fn () => Island::query()->toBase()->lazyById()->map(fn ($island) => [$island->name, $island->atoll, $island->population]),
    'islands',
)->download();
```

Anything that implements `Javaabu\Exports\Contracts\Exportable` (`headings()`, `rows()`, `totalRows()`, `filename()`, `label()`) and uses the `Javaabu\Exports\Concerns\WritesFiles` trait can be downloaded, stored and queued the same way.
