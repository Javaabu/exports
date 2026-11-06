# exports: notes for AI coding agents

`javaabu/exports` writes Eloquent models (or any rows) to CSV, XLSX and ODS with OpenSpout, streaming so memory stays flat. It adds queued exports tracked by an `Export` model, notifications, broadcasting, routes with an overridable controller and JSON API, Blade and Inertia React pages, and a policy with permissions from `javaabu/permissions`. Apps subclass `ModelExport`, so treat its public and protected methods, `Column`, `Exportable`, `WritesFiles`, `Exports`, `Route::exports()` and the JSON shape of `ExportResource` as public contracts.

## Layout

| Path | What |
| --- | --- |
| `src/ModelExport.php` | Base class: default columns, `columns()`, headings, formatting, chunked reading, queue serialization |
| `src/IterableExport.php`, `src/Contracts`, `src/Concerns/WritesFiles.php` | Non-model exports, the `Exportable` contract, `download()`/`store()`/`writeTo()`/`queue()` |
| `src/Columns/Column.php` | Column definitions |
| `src/Writers/SpreadsheetWriter.php` | OpenSpout writer: cells, formula escaping, widths, RTL |
| `src/Models/Export.php`, `src/Enums` | The Export model, `Formats`, `ExportStatuses` |
| `src/Jobs/ProcessExport.php`, `src/Events`, `src/Notifications`, `src/Commands` | The queued job, events (broadcastable), notifications, `exports:prune` |
| `src/Support` | `SerializableQuery`, `Broadcasting`, `TemporaryFile` |
| `src/Routing`, `src/Http`, `src/Policies`, `src/Views` | `Route::exports()`, controller, request, resource, policy, `ViewRenderer` |
| `config/exports.php`, `database/migrations` | Options; the `exports` table |
| `resources/views` | Blade pages (javaabu/forms components) |
| `resources/js` | Inertia React pages (TypeScript), shipped through javaabu/manifest |
| `lang/en.json`, `lang/dv.json` | Translations; English strings are the keys |
| `docs`, `llms.txt` | Docusaurus pages for docs.javaabu.com and the index for AI assistants |
| `resources/boost` | Laravel Boost guideline and skill, shipped in the package |
| `tests` | Testbench tests; two guards (`web` → `User`, `web_admin` → `Admin`); `tests/Benchmark` is the 1M-row benchmark |

## Rules

- Follow Javaabu codestyle ([boost-javaabu-guidelines](https://github.com/Javaabu/boost-javaabu-guidelines)): snake_case variables and properties, camelCase methods, early returns, `#[Test]` and `it_…` test names. Pint enforces formatting.
- Memory must stay flat: never collect rows into arrays, never `get()` a whole query, never buffer a file in memory. `composer benchmark` and `StreamingTest` guard this.
- Strings are written as text cells; never let user data become a formula.
- Nothing may assume the `web` guard or `App\Models\User`. Type-hint users as `Model`; exports belong to users through the `user` morph. Test on both guards.
- Reverb and Inertia are optional: feature-detect them, keep them in `suggest`, and test both ways.
- npm packages only the React pages or `@javaabu/exports` imports use go in the manifest `npm(..., for: NpmScopes::PAGES)` call. Only packages that a registered Blade script (`exports::progress`) imports go in the assets scope. `NpmRequirementsTest` checks that a Blade-only app isn't asked for React.
- v1 subclasses must keep working: don't rename or retype `ModelExport` hooks without an entry in `docs/upgrading.md`.
- Every user-facing string goes through `__()` with an English key, and gets a line in `lang/en.json` and `lang/dv.json` (`TranslationsTest` checks). Use Thaana in tests of user-facing behaviour. List uncertain translations in `docs/advanced-usage/translations.md`. Views use logical CSS only (`ms-`, `pe-`, `start-`), never `left`/`right`.
- Every change needs tests, docs (linked from `llms.txt`), the Boost skill if it changes how the package is used, and a line in `CHANGELOG.md`.
- Work on the `next-gen` branch; don't commit to `main`.

## Commands

```bash
composer install
composer lint       # vendor/bin/pint --test
composer format     # vendor/bin/pint
composer test       # vendor/bin/phpunit
composer benchmark  # 1M rows; EXPORTS_BENCHMARK_ROWS to change
```
