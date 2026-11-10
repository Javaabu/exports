---
title: Downloading and storing
sidebar_position: 2
---

Every export can be downloaded, stored on a disk, written to a local path, or [queued](queued-exports.md).

# Formats

| Format | Extension | Notes |
| --- | --- | --- |
| `Formats::XLSX` | `.xlsx` | The default. Bold, frozen heading row; columns sized to fit; right-to-left in Dhivehi. |
| `Formats::CSV` | `.csv` | UTF-8 with a BOM, so Excel shows Thaana correctly. |
| `Formats::ODS` | `.ods` | OpenDocument, for LibreOffice. |

The format comes from the `$format` argument (a `Javaabu\Exports\Enums\Formats` case or `'csv'`, `'xlsx'`, `'ods'`), then from the file name's extension, then from `exports.format`.

# Downloading

```php
use App\Exports\UsersExport;

public function export(Request $request)
{
    $this->authorize('viewAny', User::class);

    return UsersExport::make()->download('users.xlsx');
}
```

```php
download(?string $filename = null, Formats|string|null $format = null): BinaryFileResponse
```

The file is written to a temporary file first, so errors happen before any headers are sent, then sent and deleted. Without a file name you get `users-2026-11-04-093015.xlsx`; a name without an extension gets one.

Downloads run during the request. For more than a few tens of thousands of rows, [queue](queued-exports.md) the export instead.

# Storing on a disk

```php
$path = UsersExport::make()->store('reports/users.csv', disk: 's3');
```

```php
store(?string $path = null, ?string $disk = null, Formats|string|null $format = null): string
```

Returns the path on the disk. Without a path, the file goes in `exports.directory` with the generated name (`exports/users-2026-11-04-093015.xlsx`). Without a disk, `exports.disk` is used, then the app's default disk. The file is written locally and streamed to the disk, so it works with S3 and other remote disks.

# Writing to a local path

```php
$rows = UsersExport::make()->writeTo(storage_path('app/users.ods'));
```

Returns the number of data rows written.

# Safe files

- **No formulas in XLSX and ODS.** Strings are always written as text, so a name like `=HYPERLINK(…)` is shown, not run.
- **Escaped formulas in CSV.** A value starting with `=`, `+`, `-` or `@` (other than a number) gets an apostrophe in front, which spreadsheets hide. Turn it off with `exports.csv.escape_formulas` if a program, not a person, reads the files.
- **Temporary files** go in `exports.temporary_directory` (the system temp directory by default) and are always deleted.
