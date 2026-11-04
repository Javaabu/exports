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
