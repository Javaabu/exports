---
title: Overriding controllers and views
sidebar_position: 4
---

# Choosing the stack

```php
// config/exports.php
'views' => 'blade', // or 'inertia'
```

Requests that expect JSON always get JSON, whichever stack you choose.

# Setting up the pages

## Blade

The Blade pages use [javaabu/forms](https://github.com/Javaabu/forms) components, so they follow your `forms.framework` (Onu or Bootstrap 6). They extend your layout:

```php
// config/exports.php
'layout' => 'layouts.app',     // the view the pages @extends
'layout_section' => 'content', // the section they fill
```

Live progress on the Blade pages comes from a small script, `exports::progress`, delivered by [javaabu/manifest](https://github.com/Javaabu/manifest). The pages require it themselves; your app needs manifest's Vite plugin:

```js
// vite.config.js
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
    plugins: [
        laravel({ input: ["resources/css/app.css", "resources/js/app.js"] }),
        manifest(),
    ],
})
```

The pages use Tailwind utilities from Onu's theme (logical ones only, so they work right-to-left). Let Tailwind see them:

```css
/* resources/css/app.css */
@source "../../vendor/javaabu/exports/resources/views";
```

The Blade pages need no React. The progress script imports no npm packages, so `php artisan manifest:check` asks a Blade-only app for nothing beyond `vite` and `laravel-vite-plugin` on exports' behalf. javaabu/forms may list its own Blade assets' packages.

## Inertia React

The pages are `exports::Exports/Index` and `exports::Exports/Show`, written in TypeScript with [@javaabu/forms](https://github.com/Javaabu/react-forms) and [@javaabu/onu](https://www.npmjs.com/package/@javaabu/onu). javaabu/manifest ships them to your app; resolve them with `resolvePage()`:

```tsx
// resources/js/app.tsx
import { createInertiaApp } from "@inertiajs/react"
import { resolvePage } from "@javaabu/manifest/inertia"
import { createRoot } from "react-dom/client"

createInertiaApp({
    resolve: (name) => resolvePage(name, import.meta.glob("./pages/**/*.tsx")),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
```

Install the npm packages the pages need, and check them:

```bash
composer require inertiajs/inertia-laravel
php artisan manifest:check   # prints the install command for anything missing
```

They need `@javaabu/forms`, `@javaabu/onu`, `react`, `react-dom` and `@inertiajs/react`. exports declares them for its pages only (manifest's `pages` scope). `manifest:check` asks for them once your `package.json` has an Inertia adapter (`@inertiajs/react`), or when you run `php artisan manifest:check --scope=pages`. Importing `@javaabu/exports` in your own React code needs the same packages. Wrap your app in `FormsProvider` (with the Onu and Inertia adapters) as @javaabu/forms describes, so forms submit as Inertia visits. Your persistent layout wraps the pages as it wraps your own.

The pages' text comes from the server, translated, in the `strings` prop, so they're in Dhivehi when the app's locale is `dv`. For live progress over Reverb, set up Laravel Echo as `window.Echo`; otherwise the pages poll.

You can also use the pages' parts in your own pages:

```tsx
import { ExportActions, ExportProgress, useExportProgress } from "@javaabu/exports"
import type { ExportData } from "@javaabu/exports"
```

# Page props

| Prop | Pages | What |
| --- | --- | --- |
| `title` | both | The translated page title |
| `broadcasting` | both | Whether progress is broadcast; poll otherwise |
| `poll_interval` | both | Milliseconds between polls |
| `routes` | both | Route names by action, e.g. `routes.show` is `admin.exports.show` |
| `urls` | both | `index` and `store` URLs |
| `strings` | both (Inertia only) | The pages' translated text |
| `exports` | index | A paginator (Blade) or a paginated `ExportResource` collection (Inertia) |
| `available` | index | Registered exports the user may start: `[{ type, label }]` |
| `formats` | index | `[{ value, label }]`, from `exports.formats` |
| `statuses` | index | `[{ value, label }]` |
| `sees_others` | index | Whether the user sees everyone's exports |
| `filters` | index | `{ status, type, mine }` from the query string |
| `export` | show | The Export model (Blade) or an `ExportResource` array (Inertia) |

# Overriding Blade views

```bash
php artisan vendor:publish --tag=exports-views
```

Laravel uses `resources/views/vendor/exports/exports/index.blade.php` in place of the package's. Override one view and leave the rest: `exports.index`, `exports.show`, and the partials `exports.partials.row`, `exports.partials.progress` and `exports.partials.actions`. Keep the `data-export-*` attributes if you want live progress.

# Overriding Inertia pages

Copy a page into your app and edit it:

```bash
php artisan manifest:override exports js/Pages/Exports/Index.tsx
```

It goes to `resources/vendor/exports/js/Pages/Exports/Index.tsx`, and `resolvePage()` uses it instead of the package's.

# Overriding the controller

Extend the controller and override what you need:

```php
namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Javaabu\Exports\Http\Controllers\ExportsController as BaseExportsController;

class ExportsController extends BaseExportsController
{
    // Only show exports from this tenant
    protected function query(Request $request): Builder
    {
        return parent::query($request)->where('tenant_id', $request->user()->tenant_id);
    }
}
```

Then use it in one of three ways:

```php
// config/exports.php: every Route::exports() call
'controllers' => [
    'exports' => \App\Http\Controllers\ExportsController::class,
],
```

```php
// one registration
Route::exports(['controller' => \App\Http\Controllers\ExportsController::class]);
```

```php
// the container, in a service provider
$this->app->bind(
    \Javaabu\Exports\Http\Controllers\ExportsController::class,
    \App\Http\Controllers\ExportsController::class,
);
```

Useful methods to override: `query()`, `findExport()`, `makeExportable()`, `mayRun()`, `availableExports()`, `formats()`, `meta()`, `strings()` and `downloadResponse()`.
