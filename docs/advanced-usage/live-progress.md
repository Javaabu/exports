---
title: Live progress
sidebar_position: 3
---

The exports page updates as exports run. With [Laravel Reverb](https://reverb.laravel.com) installed, progress is broadcast; without it, the page polls. Nothing else changes.

# Turning it on

```bash
php artisan install:broadcasting   # installs Reverb and Laravel Echo
```

With `exports.broadcasting.enabled` set to `auto` (the default), progress is broadcast when `laravel/reverb` is installed and the default broadcaster isn't `null` or `log`. Set it to `true` to broadcast with another broadcaster (Pusher, Ably), or `false` to always poll.

# Channels

| Channel | Who may listen | |
| --- | --- | --- |
| `private-exports.{uuid}` | Users who may `view` the export | One export |
| `private-exports.owner.{type}.{id}` | The owner only | Every export of a user; `{type}` is the morph type with `\` replaced by `-` |

The package authorizes both. The guards that authenticate the channels are `exports.broadcasting.guards` (null: the defaults). Change the `exports` prefix with `exports.broadcasting.channel`.

| Event | Broadcast as |
| --- | --- |
| `ExportStarted` | `export.started` |
| `ExportProgressed` | `export.progressed` |
| `ExportCompleted` | `export.completed` |
| `ExportFailed` | `export.failed` |
| `ExportCancelled` | `export.cancelled` |

They're broadcast immediately (`ShouldBroadcastNow`), from the job, every `exports.queue.progress_every` rows. Each sends:

```json
{
    "id": "9d3c…",
    "status": "processing",
    "status_label": "Processing",
    "progress": 25,
    "total_rows": 200000,
    "processed_rows": 50000,
    "is_finished": false,
    "is_downloadable": false,
    "error": null
}
```

# Listening in your own pages

The API's `broadcast_channel` and `meta.broadcasting` tell a page what to do:

```ts
import { useEcho } from '@laravel/echo-react';

useEcho(`exports.${exportId}`, ['.export.progressed', '.export.completed', '.export.failed', '.export.cancelled'], (payload) => {
    setExport((current) => ({ ...current, ...payload }));
});
```

```js
// Blade, with Laravel Echo
Echo.private(`exports.${exportId}`)
    .listen('.export.progressed', (payload) => updateRow(payload))
    .listen('.export.completed', (payload) => updateRow(payload));
```

Note the leading dot: the events use `broadcastAs()` names.

# Polling

When `meta.broadcasting` is `false`, poll `GET /exports/{export}` (with `Accept: application/json`) every `meta.poll_interval` milliseconds (`exports.broadcasting.poll_interval`, 3000) until `is_finished` is true. The package's pages do this themselves.
