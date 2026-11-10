{{--
    javaabu/exports guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

## javaabu/exports

- This project exports models to CSV, XLSX and ODS with `javaabu/exports`. Write exports as classes extending `Javaabu\Exports\ModelExport` and call `download()`, `store()` or `queue($user)` on them; don't use maatwebsite/excel for them.
- Always activate the `javaabu-exports` skill when writing or changing an export, adding an export button or the exports page, queueing an export, or changing who may export.
