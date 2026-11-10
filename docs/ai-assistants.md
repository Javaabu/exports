---
title: AI assistants
sidebar_position: 1.35
---

The docs are plain Markdown, written for people and AI coding assistants alike. The package ships them, so they always match the version you have installed.

| File | What |
| --- | --- |
| `vendor/javaabu/exports/llms.txt` | The rules for using the package, and every page |
| `vendor/javaabu/exports/docs/` | The pages |
| `vendor/javaabu/exports/resources/boost/` | The Laravel Boost skill and guideline |

# Laravel Boost

[Laravel Boost](https://github.com/laravel/boost) finds the skill and guideline by itself in Composer packages. Run this after installing or updating the package:

```bash
php artisan boost:update
```

Choose `javaabu/exports` if Boost asks which third-party packages to use.

# Other assistants

Point your assistant at the docs from the project's instructions file (`AGENTS.md`, `CLAUDE.md`, `.cursor/rules`, `.github/copilot-instructions.md`):

```md
## Exports

Spreadsheet exports use javaabu/exports. Before writing an export, read vendor/javaabu/exports/llms.txt.
```

Or copy `vendor/javaabu/exports/resources/boost/skills/javaabu-exports` into your assistant's skills folder, such as `.claude/skills/`.
