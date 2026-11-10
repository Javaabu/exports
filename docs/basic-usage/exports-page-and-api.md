---
title: Exports page and API
sidebar_position: 4
---

The package has routes where users start exports, follow their progress, and download, cancel and delete them. Every route answers JSON when the request expects it (`Accept: application/json`), and renders a page otherwise.

# Registering the routes

Routes aren't loaded automatically. Register them inside your own group:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::exports();
});
```

For an admin panel on its own guard:

```php
Route::middleware('web')->group(function () {
    Route::exports([
        'guard' => 'web_admin',
        'prefix' => 'admin/exports',
        'name' => 'admin.exports',
    ]);
});
```

| Option | Default | |
| --- | --- | --- |
| `guard` | `null` (the default guard) | Used for the default `auth:{guard}` middleware |
| `prefix` | `exports` | The URL prefix |
| `name` | `exports` | The route name prefix |
| `middleware` | `['auth']` or `["auth:{guard}"]` | Replaces the default middleware of the authenticated routes |
| `controller` | `config('exports.controllers.exports')` | See [Overriding controllers and views](../advanced-usage/overriding-controllers-and-views.md) |
| `signed` | `true` | Also register the signed download route |
| `signed_middleware` | `['signed']` | Middleware of the signed download route |

| Method | URL | Name | Ability |
| --- | --- | --- | --- |
| `GET` | `/exports` | `exports.index` | `viewAny` |
| `POST` | `/exports` | `exports.store` | `create`, and the export's `authorize()` |
| `GET` | `/exports/{export}` | `exports.show` | `view` |
| `GET` | `/exports/{export}/download` | `exports.download` | `download` |
| `POST` | `/exports/{export}/cancel` | `exports.cancel` | `cancel` |
| `DELETE` | `/exports/{export}` | `exports.destroy` | `delete` |
| `GET` | `/exports/{export}/signed-download` | `exports.signed-download` | A valid signature (no login) |

`{export}` is the export's UUID. Notifications link to `exports.signed-download`; if you register the routes under another name, set `exports.routes.signed_download` to match (for example `admin.exports.signed-download`).

# Letting users start exports

The page and `POST /exports` start exports by a registered name. Register the exports users may start, in a service provider's `boot()`:

```php
use App\Exports\UsersExport;
use Javaabu\Exports\Exports;

Exports::register('users', UsersExport::class);

Exports::register([
    'users' => UsersExport::class,
    'payments' => PaymentsExport::class,
]);
```

A user may start an export when they have `view_exports` (the `create` ability) and the export's `authorize()` allows it. For a `ModelExport`, that's the `viewAny` ability on its model, so users only export what they can list:

```php
public function authorize(?Model $user): bool
{
    return $user?->can('export', User::class) ?? false;
}
```

To build the export from the request, for example with filters, override `fromRequest()`:

```php
use Illuminate\Http\Request;

public static function fromRequest(Request $request): static
{
    return new static(User::query()->where('status', $request->input('status')));
}
```

Validate anything you read from the request: `fromRequest()` runs after the package validates `type`, `format` and `filename` only.

The exports page lists only the registered exports the user may start.

# The JSON API

## List exports

```http
GET /exports?status=processing&type=users&mine=1&per_page=20
Accept: application/json
```

Users with `view_exports` see their own exports; users with `view_others_exports` see everyone's (`mine=1` narrows it to their own). Newest first, paginated.

```json
{
    "data": [
        {
            "id": "9d3c…",
            "type": "users",
            "name": "Users",
            "format": "xlsx",
            "format_label": "Excel (XLSX)",
            "status": "processing",
            "status_label": "Processing",
            "progress": 25,
            "total_rows": 200000,
            "processed_rows": 50000,
            "filename": "users-2026-11-04-093015.xlsx",
            "size": null,
            "is_finished": false,
            "is_downloadable": false,
            "is_cancellable": true,
            "user": { "type": "user", "id": 1, "name": "އަހުމަދު" },
            "can": { "download": true, "cancel": true, "delete": true },
            "links": {
                "show": "https://example.mv/exports/9d3c…",
                "download": null,
                "cancel": "https://example.mv/exports/9d3c…/cancel",
                "destroy": "https://example.mv/exports/9d3c…"
            },
            "broadcast_channel": "exports.9d3c…",
            "started_at": "2026-11-04T09:30:15+05:00",
            "completed_at": null,
            "failed_at": null,
            "cancelled_at": null,
            "expires_at": null,
            "created_at": "2026-11-04T09:30:15+05:00",
            "updated_at": "2026-11-04T09:31:02+05:00"
        }
    ],
    "links": { "…": "…" },
    "meta": {
        "current_page": 1,
        "broadcasting": true,
        "poll_interval": 3000,
        "routes": { "index": "exports.index", "store": "exports.store", "show": "exports.show", "download": "exports.download", "cancel": "exports.cancel", "destroy": "exports.destroy" }
    }
}
```

`error` is included for failed exports. `broadcast_channel` is null when [live progress](../advanced-usage/live-progress.md) is off; poll `GET /exports/{export}` every `meta.poll_interval` milliseconds instead.

## Start an export

```http
POST /exports
Content-Type: application/json

{ "type": "users", "format": "csv", "filename": "ބޭނުންތެރިން" }
```

`201` with the export. `format` (one of `exports.formats`) and `filename` are optional; slashes and other characters file systems reject are replaced in the file name. `422` for an unknown type or format, `403` when the user may not run it.

## Follow, download, cancel, delete

| Request | Response |
| --- | --- |
| `GET /exports/{export}` | The export, with `meta` as above |
| `GET /exports/{export}/download` | The file; `404` until it's completed, `410` once it has expired |
| `POST /exports/{export}/cancel` | The export; `409` if it already finished |
| `DELETE /exports/{export}` | `204`; the file is deleted too |

# Pages

Without `Accept: application/json`, `index` and `show` render a page, and `store`, `cancel` and `destroy` redirect with a `success` or `error` flash message.

| Page | Blade view | Inertia page |
| --- | --- | --- |
| List, with the form to start an export | `exports::exports.index` | `exports::Exports/Index` |
| One export | `exports::exports.show` | `exports::Exports/Show` |

The list shows each export's name, file name, format, status, a progress bar, rows, who started it (for users who see others' exports), when, and download, cancel and delete buttons. Rows update live while exports run, then the page reloads to show the download button.

Which stack renders them is set by `exports.views`: `blade` (default) or `inertia`. Both need a little setup in the app (a layout, or the Inertia page resolver, and manifest's Vite plugin). See [Overriding controllers and views](../advanced-usage/overriding-controllers-and-views.md), which also lists every page prop.
