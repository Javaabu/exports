---
title: Introduction
sidebar_position: 1.0
---

# Exports

[javaabu/exports](https://github.com/Javaabu/exports) exports Eloquent models to CSV, XLSX and ODS files.

- **Smart defaults.** Point an export at a model and you get its table columns, minus hidden attributes, passwords and tokens, with foreign keys replaced by the related model's name, readable (and translatable) headings, enum labels, "Yes"/"No" for booleans and dates in the app timezone.
- **Streaming.** Rows are read a chunk at a time and written with [OpenSpout](https://github.com/openspout/openspout), so memory stays flat. A million rows export with a few megabytes of memory.
- **Background exports.** Large exports run on the queue, write to any filesystem disk, report progress, can be cancelled, and notify the user with a signed, expiring download link.
- **An exports page and API.** Routes, an overridable controller, a JSON API and Blade or Inertia React pages where users follow progress live (over Laravel Reverb when it's installed, polling otherwise) and download their files.
- **Permissions.** A policy and default permissions through [javaabu/permissions](https://github.com/Javaabu/permissions), for any guard and user model.
- **Dhivehi.** Every string is translated, and XLSX sheets are right-to-left in Dhivehi.

```php
use App\Models\User;
use Javaabu\Exports\ModelExport;

class UsersExport extends ModelExport
{
    public function modelClass(): string
    {
        return User::class;
    }
}

// In a controller
return UsersExport::make()->download('users.xlsx');

// Or on the queue, for big exports
UsersExport::make(User::query()->where('is_active', true))->queue($request->user());
```

Start with [Installation & Setup](installation-and-setup.md), then [Model exports](basic-usage/model-exports.md). Coming from v1? Read [Upgrading](upgrading.md).
