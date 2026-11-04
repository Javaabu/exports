---
name: javaabu-exports
description: Export Eloquent models or other rows to CSV, XLSX or ODS with javaabu/exports in a Laravel app. Use whenever the project depends on javaabu/exports and the task writes or changes an export class, chooses export columns or headings, adds an export or download button, exports a large table, queues an export or shows its progress, sets up the exports page or API, or changes who may export or download.
license: MIT
metadata:
  author: Javaabu
---

# Exports with javaabu/exports

Exports stream: rows are read a chunk at a time and written with OpenSpout, so memory stays flat for millions of rows. The docs ship with the package: `vendor/javaabu/exports/llms.txt` lists every page in `vendor/javaabu/exports/docs/`.

## Writing an export

```php
namespace App\Exports;

use App\Models\User;
use Javaabu\Exports\Columns\Column;
use Javaabu\Exports\ModelExport;

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
            'status',               // enum: its label
            'role.name',            // relation, eager loaded per chunk
            'organizations.name',   // to-many: joined with ", "
            Column::make('national_id')->label(__('National ID')),
            Column::make('created_at')->format(fn ($value) => $value->toDateString()),
        ];
    }
}
```

Without `columns()`, the export takes the table columns minus hidden attributes and secrets, and replaces `role_id` with the `role` relation. Often no `columns()` is needed at all.

## Using it

```php
// small exports, during the request
return UsersExport::make()->download('users.xlsx');

// a filtered query
UsersExport::make(User::query()->where('status', 'approved'))->download('approved-users.csv');

// to a disk
$path = UsersExport::make()->store('reports/users.xlsx', disk: 's3');

// big exports, on the queue; the user is notified with a download link
$export = UsersExport::make($query)->queue($request->user(), format: 'xlsx');

return redirect()->route('exports.show', $export);
```

Authorize before exporting, as for listing: `$this->authorize('viewAny', User::class);`.

## The exports page

```php
// routes/web.php, inside the app's group
Route::exports();
Route::exports(['guard' => 'web_admin', 'prefix' => 'admin/exports', 'name' => 'admin.exports']);

// AppServiceProvider::boot()
Exports::register('users', UsersExport::class);
```

Permissions: `view_exports`, `view_others_exports`, `delete_exports`, `delete_others_exports`. Run `php artisan permissions:sync`. Schedule `exports:prune` daily.

## Rules

- Don't load models yourself (`User::all()`, `->get()`) or map rows by hand; pass a query and let the export stream it.
- Don't call `with()` for the columns' relations; the export eager loads them. Constrained eager loads you add yourself are kept.
- Keep the primary key in any `select()`. An `orderBy()` makes the export page with offsets; drop it for huge tables.
- Override `formatValue()` for a single attribute and call `parent::formatValue()` for the rest.
- An export passed to `queue()` is serialized: hold a query, ids and filters, not collections.
- Use `queue()` beyond a few tens of thousands of rows.
- Headings go through `__()`: add Dhivehi for new headings to `lang/dv.json`.
- Don't use maatwebsite/excel concerns (`WithHeadings`, `FromQuery`, `Excel::download()`) with these classes.

See [references/common-mistakes.md](references/common-mistakes.md) for more, and [references/queued-exports.md](references/queued-exports.md) for the Export model, events and live progress.
