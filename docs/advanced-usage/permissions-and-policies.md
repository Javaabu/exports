---
title: Permissions and policies
sidebar_position: 2
---

# Permissions

The package registers these permissions with [javaabu/permissions](https://github.com/Javaabu/permissions), in the `exports` group (labelled "Exports"), for every guard in `config('permissions.guards')`:

| Permission | Description | Allows |
| --- | --- | --- |
| `view_exports` | View own exports | Listing, starting, following and downloading your own exports |
| `view_others_exports` | View other users' exports | The same for everyone's exports |
| `delete_exports` | Delete own exports | Cancelling and deleting your own exports |
| `delete_others_exports` | Delete other users' exports | The same for everyone's exports |

Run `php artisan permissions:sync` after installing or updating the package. Give them to roles in `config/permissions.php` or from the roles page, for example:

```php
// config/permissions.php
'roles' => [
    'super_admin' => ['*'],
    'staff' => ['view_exports', 'delete_exports'],
],
```

To register your own permissions instead, set `exports.register_permissions` to `false` and register a policy for the Export model yourself.

# The policy

`Javaabu\Exports\Policies\ExportPolicy` extends javaabu/permissions' `ResourcePolicy`:

| Ability | Needs |
| --- | --- |
| `viewAny`, `create` | `view_exports` or `view_others_exports` |
| `viewOthers` | `view_others_exports` |
| `view`, `download` | `view_others_exports`, or `view_exports` and owning the export |
| `cancel`, `delete` | `delete_others_exports`, or `delete_exports` and owning the export |
| `update`, `restore`, `forceDelete`, `viewTrash` | Never |

Owning means the export's `user` morph is the user (`$export->isOwnedBy($user)`).

Starting an export also needs the export's own `authorize($user)` to pass. For a `ModelExport` that's `viewAny` on its model. See [Exports page and API](../basic-usage/exports-page-and-api.md#letting-users-start-exports).

# Overriding the policy

The app wins over the package. Either:

```php
// config/permissions.php
'policies' => [
    \Javaabu\Exports\Models\Export::class => \App\Policies\ExportPolicy::class,
],
```

or, in any service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::policy(\Javaabu\Exports\Models\Export::class, \App\Policies\ExportPolicy::class);
```

Extend the package policy to change one ability:

```php
namespace App\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Exports\Policies\ExportPolicy as BaseExportPolicy;

class ExportPolicy extends BaseExportPolicy
{
    public function cancel(Model $user, Model $export): bool
    {
        return $this->view($user, $export);
    }
}
```

`exports.policy` sets the default policy the package registers; prefer the two options above in apps.

# Multiple guards and user models

Nothing assumes the `web` guard or `App\Models\User`:

- Exports belong to any model through the `user` morph. `queue($admin)` and the routes record whoever is logged in on the route's guard.
- Register the routes once per guard, each with its own prefix and name. Each route group only answers to its guard's users.
- Permissions are synced for each guard in `config('permissions.guards')`, so `view_exports` on `web_admin` is separate from `view_exports` on `web`.
- A user with `view_others_exports` sees every export in the table, whichever guard started it.

```php
Route::middleware('web')->group(function () {
    Route::exports(); // users on the default guard

    Route::exports(['guard' => 'web_admin', 'prefix' => 'admin/exports', 'name' => 'admin.exports']);
});
```

User models need javaabu/permissions' `HasRoles` trait, and `Notifiable` for notifications. If their keys aren't integers, set `exports.user_key_type` before migrating.
