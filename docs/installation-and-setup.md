---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

```bash
composer require javaabu/exports
```

The service provider is discovered automatically. Nothing else is needed to [download](basic-usage/downloading-and-storing.md) or [store](basic-usage/downloading-and-storing.md) exports.

# Migrating

[Queued exports](basic-usage/queued-exports.md) are tracked in an `exports` table. The package loads its migration itself:

```bash
php artisan migrate
```

Exports belong to whoever started them through a `user` morph, so any user model works. If your user models have UUID, ULID or string keys, set `user_key_type` before migrating:

```bash
php artisan vendor:publish --tag=exports-config
```

```php
// config/exports.php
'user_key_type' => 'uuid', // integer (default), uuid, ulid or string
```

To change the migration itself, publish it and stop the package from loading its copy:

```bash
php artisan vendor:publish --tag=exports-migrations
```

```php
// AppServiceProvider::register()
\Javaabu\Exports\Exports::ignoreMigrations();
```

# Permissions

The package registers four permissions with [javaabu/permissions](https://github.com/Javaabu/permissions). Write them to the database, as on every deploy:

```bash
php artisan permissions:sync
```

| Permission | Allows |
| --- | --- |
| `view_exports` | Seeing, starting and downloading your own exports |
| `view_others_exports` | Seeing and downloading everyone's exports |
| `delete_exports` | Cancelling and deleting your own exports |
| `delete_others_exports` | Cancelling and deleting everyone's exports |

See [Permissions and policies](advanced-usage/permissions-and-policies.md).

# The exports page

Register the routes inside your own route group, so you choose the prefix, middleware and guard:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::exports();
});
```

Then register the exports users may start from that page:

```php
// AppServiceProvider::boot()
use App\Exports\UsersExport;
use Javaabu\Exports\Exports;

Exports::register('users', UsersExport::class);
```

See [Exports page and API](basic-usage/exports-page-and-api.md).

# Cleaning up

Files expire after 7 days (`exports.expire_after_days`). Schedule the prune command to delete them:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('exports:prune')->daily();
```

# Publishing

| Tag | What |
| --- | --- |
| `exports-config` | `config/exports.php` |
| `exports-migrations` | The `exports` table migration |
| `exports-views` | The Blade views, to `resources/views/vendor/exports` |

Every option is described in [Configuration](advanced-usage/configuration.md).
