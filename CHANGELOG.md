# Changelog

All notable changes will be documented in this file.

## 2.0.0 - Unreleased

- Requires PHP 8.4 and Laravel 12 or 13.
- Replaced maatwebsite/excel with OpenSpout: CSV, XLSX and ODS are streamed with flat memory. See `docs/upgrading.md`.
- Exports read with `lazyById()` (or `lazy()` for ordered queries), eager loading per chunk. A million rows export with a few MB of memory.
- Smarter defaults: hidden attributes, passwords and tokens are left out, foreign keys are replaced by their relation, headings come from validation attribute names, the model or a readable headline (translated), enums use their label, booleans are "Yes"/"No", dates are in the app timezone.
- `columns()` with dot-notation relations and `Column` definitions (label, format, computed value).
- `IterableExport` for rows that aren't models.
- CSV values that would run as formulas are escaped; XLSX strings are never written as formulas. XLSX columns are sized to fit, and sheets are right-to-left in Dhivehi.
- `queue()` writes exports on the queue and returns an `Export` record (status, progress, file, user morph). `ProcessExport` streams the file to a configurable disk, saves progress, stops when cancelled, and notifies the user (mail by default) with a signed, expiring download link.
- `exports:prune` deletes expired exports and their files.
- Export events: `ExportStarted`, `ExportProgressed`, `ExportCompleted`, `ExportFailed`, `ExportCancelled`.
- `Route::exports()` registers the exports routes (index, store, show, download, cancel, destroy, signed download) with the app's prefix, name, guard and middleware. `ExportsController` answers JSON or renders pages, and can be replaced from config, the macro or the container.
- `ExportPolicy` and the permissions `view_exports`, `view_others_exports`, `delete_exports`, `delete_others_exports`, registered with javaabu/permissions. Exports belong to any user model through a morph; nothing assumes the `web` guard.
- `Exports::register('users', UsersExport::class)` lets the API start an export by name.
- Live progress: export events broadcast on private channels (`exports.{uuid}`, `exports.owner.{type}.{id}`) when laravel/reverb is installed and broadcasting is on (`exports.broadcasting.enabled`: auto, true or false). The API tells pages whether to listen or to poll, and how often.
- Exports pages in Blade (javaabu/forms v2 components) and Inertia React (TypeScript, @javaabu/forms and @javaabu/onu, shipped through javaabu/manifest), chosen with `exports.views`, with live progress, in English and Dhivehi, right-to-left ready.
