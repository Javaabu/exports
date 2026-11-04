---
title: Dhivehi and translations
sidebar_position: 5
---

Every string the package shows (statuses, formats, messages, notifications, permission descriptions, page text) goes through `__()` with an English key. The package ships `lang/en.json` and `lang/dv.json`; Laravel loads them automatically.

To change a translation, add the same key to your app's `lang/dv.json`. App lines win over package lines.

# Headings and values in files

- Default headings are translated: add the English headline (`"Island": "ރަށް"`) to `lang/dv.json`, or use `validation.attributes`, or the model's `exportHeadings()`. See [Model exports](../basic-usage/model-exports.md#headings).
- `Yes` and `No` are `އާނ` and `ނޫން`.
- Files are written in the current locale. Queued exports remember the locale that was active when `queue()` was called, so a user browsing in Dhivehi gets Dhivehi headings even though the worker runs in English.

# Thaana in files

- CSV files start with a UTF-8 BOM so Excel shows Thaana instead of question marks.
- XLSX sheets are right-to-left when the locale is in `exports.rtl_locales` (or `exports.rtl` is `true`).
- File names may be in Thaana: `ބޭނުންތެރިން.xlsx`. Downloads send them with `filename*` (RFC 6266).

# Right-to-left pages

The Blade and React pages use logical utilities only (`ms-`, `pe-`, `start-`, `text-start`, `inline-size`), so they mirror when the page is `dir="rtl"`; a test rejects physical ones. Progress bars fill from the inline start. File names and other free text get `dir="auto"`.

# Strings for review

These Dhivehi translations were written by the package authors and should be checked by a native speaker:

| English | Dhivehi | Note |
| --- | --- | --- |
| Pending | މަޑުކުރަނީ | "Waiting"; an alternative is ފެށިފައެއް ނުވޭ |
| Processing | ތައްޔާރުކުރަނީ | "Being prepared" |
| Cancelled | ކެންސަލްކުރެވިއްޖެ | Loanword |
| Excel (XLSX) | އެކްސެލް (XLSX) | |
| OpenDocument (ODS) | އޯޕަން ޑޮކިއުމަންޓް (ODS) | |
| View other users' exports | އެހެން ފަރާތްތަކުގެ އެކްސްޕޯޓްތައް ބެލުން | "Other parties'" |
| Your :name export could not be completed… | ތިބާގެ :name އެކްސްޕޯޓް ނިންމޭކަށް ނުކެރުނު… | Tone of the apology |
| Your export has started. We will let you know when it is ready. | އެކްސްޕޯޓް ފެށިއްޖެ. ތައްޔާރުވުމުން އަންގާނަން. | |
| Progress | ކުރިއެރުން | Literally "advancement"; ޕްރޮގްރެސް is also used |
| Started by | ފެށީ | Column heading for who started the export |
| Actions | ޢަމަލުތައް | |
| Filter | ފިލްޓަރ | Loanword |
| :processed of :total rows | :total ރޯގެ ތެރެއިން :processed | "Rows" as ރޯ (loanword) |
