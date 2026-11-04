---
title: Requirements
sidebar_position: 1.1
---

- PHP 8.4 or 8.5, with the `zip`, `xmlreader` and `dom` extensions (for XLSX and ODS)
- Laravel 12 or 13
- [javaabu/permissions](https://github.com/Javaabu/permissions) v3, [javaabu/forms](https://github.com/Javaabu/forms) v2 and [javaabu/manifest](https://github.com/Javaabu/manifest) v1, installed with this package
- For the exports pages: Vite with manifest's plugin. The Inertia React pages also need `@javaabu/forms`, `@javaabu/onu`, React 18.3 or 19 and `@inertiajs/react` 2 or 3 in the app (`php artisan manifest:check` lists them).

Optional:

| Package | For |
| --- | --- |
| `laravel/reverb` ^1.12 | Live progress on the exports page. Without it, the page polls. See [Live progress](advanced-usage/live-progress.md). |
| `inertiajs/inertia-laravel` ^2.0 | The Inertia React pages (`exports.views` = `inertia`). See [Exports page and API](basic-usage/exports-page-and-api.md). |

A queue worker is needed for [queued exports](basic-usage/queued-exports.md). With the `sync` queue they run during the request.
