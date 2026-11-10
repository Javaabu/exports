# Common mistakes with javaabu/exports

## Loading everything

```php
// Wrong: loads every user into memory
$users = User::with('role')->get();
return IterableExport::make(['Name'], $users->map(fn ($user) => [$user->name]))->download();

// Right: the export streams the query and eager loads per chunk
return UsersExport::make(User::query())->download();
```

## Using v1 / maatwebsite APIs

```php
// Wrong (v1)
return Excel::download(new UsersExport, 'users.xlsx');

// Right
return UsersExport::make()->download('users.xlsx');
```

## Overriding formatValue without the parent

```php
// Wrong: every other column loses its formatting (enums, booleans, dates, relations)
public function formatValue(Model $model, string $attribute, mixed $value): mixed
{
    return $attribute === 'amount' ? $value / 100 : $value;
}

// Right
public function formatValue(Model $model, string $attribute, mixed $value): mixed
{
    if ($attribute === 'amount') {
        return $value / 100;
    }

    return parent::formatValue($model, $attribute, $value);
}
```

## Unserializable queued exports

```php
// Wrong: a loaded collection and a closure can't go on the queue
class UsersExport extends ModelExport
{
    public function __construct(public Collection $users, public Closure $filter) {}
}

// Right: pass a query (serialized as SQL) or plain values
UsersExport::make(User::query()->where('status', 'approved'))->queue($user);
```

## Selecting without the key

```php
// Wrong: lazyById() needs the primary key
UsersExport::make(User::query()->select('name', 'email'));

// Right
UsersExport::make(User::query()->select('id', 'name', 'email'));
```

## Hard-coding the guard or user model

```php
// Wrong
Route::exports(['middleware' => ['auth:web']]); // and then using it for admins

// Right: one registration per guard
Route::exports(['guard' => 'web_admin', 'prefix' => 'admin/exports', 'name' => 'admin.exports']);
```

## Changing the package policy in place

Override it in the app instead:

```php
// config/permissions.php
'policies' => [
    \Javaabu\Exports\Models\Export::class => \App\Policies\ExportPolicy::class,
],
```

## Signed links with renamed routes

If the routes are registered as `admin.exports`, set `exports.routes.signed_download` to `admin.exports.signed-download`, or notification emails have no download button.
