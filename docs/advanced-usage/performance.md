---
title: Performance
sidebar_position: 1
---

Exports stream: rows are read a chunk at a time and written as they're read, so memory depends on the chunk size, not on how many rows there are.

# Benchmark

`tests/Benchmark/MillionRowsTest.php` exports 1,000,000 users with an eager-loaded relation from SQLite (chunk size 1000). It runs in CI on every push.

| Format | Time (CI) | Peak memory above the baseline | File |
| --- | --- | --- | --- |
| CSV | 157 s | +4.8 MB | 111 MB |
| XLSX | 190 s | +3.6 MB | 30 MB |

Run it yourself with `composer benchmark`, and change the number of rows with `EXPORTS_BENCHMARK_ROWS`.

# How rows are read

`ModelExport::models()` reads with:

- `lazyById(chunk_size)` when the query has no `orderBy()`. Each chunk is `where id > ? order by id limit 1000`, which stays fast deep into big tables.
- `lazy(chunk_size)` when it has one, so your order is kept. That uses `limit … offset …`, which gets slower on very large tables; order by an indexed column, or drop the order for huge exports.

Relations used by the columns are eager loaded once per chunk.

Tips:

- Keep the primary key in any `select()`; `lazyById()` needs it.
- Raise `exports.chunk_size` (1000) for narrow rows, lower it for wide rows with many relations.
- Prefer `belongsTo` columns over to-many ones on huge exports: each to-many relation is another query per chunk and more memory per row.
- `totalRows()` runs one `count(*)` for progress. Override it to return `null` if counting is expensive; progress is then unknown until the end.

# How rows are written

[OpenSpout](https://github.com/openspout/openspout) writes each row to disk as soon as it's added. XLSX files use inline strings (no shared-strings table in memory). Column widths are tracked as the widest value per column, which costs one integer per column.

Queued exports write the whole file in one job, because XLSX and ODS can't be appended to by parallel jobs. Give big exports a dedicated queue and a worker timeout above `exports.queue.timeout`:

```bash
php artisan queue:work --queue=exports --timeout=3700
```

```php
// config/exports.php
'queue' => [
    'queue' => 'exports',
    'timeout' => 3600,
],
```
