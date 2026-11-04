---
title: API reference
sidebar_position: 20
---

Every public class and method. Namespaces are under `Javaabu\Exports`.

# ModelExport

`abstract class ModelExport implements Contracts\Exportable`, using `Concerns\WritesFiles`.

| Method | |
| --- | --- |
| `__construct(Builder\|Relation\|null $query = null)` | |
| `static make(mixed ...$arguments): static` | `new static(...$arguments)` |
| `abstract modelClass(): string` | The model class |
| `columns(): array` | `string\|Column` per column; defaults to `allowedAttributes()` |
| `allowedAttributes(): array` | The default columns |
| `relationsToInclude(): array` | Relations appended to the default columns |
| `query(): Builder\|Relation` | The query, with `eagerLoads()` added |
| `eagerLoads(): array` | Relations the columns need |
| `models(): LazyCollection` | The models, a chunk at a time |
| `rows(): iterable` | `map()` of every model |
| `totalRows(): ?int` | `count(*)` of the query |
| `chunkSize(): int` | `exports.chunk_size` |
| `map(Model $model): array` | One row |
| `formatValue(Model $model, string $attribute, mixed $value): mixed` | Formats a value |
| `getEnumLabel(Model $model, string $attribute, UnitEnum $value): string` | |
| `displayName(Model $model): mixed` | How a related model is shown |
| `isAdminModel(…)`, `isAdminModelCollection(…)` | v1 helpers |
| `headings(): array` | One per column |
| `resolvedColumns(): array` | The columns as `Column` instances |
| `filename(): string` | `users` |
| `label(): string` | `Users`, translated |
| `authorize(?Model $user): bool` | May the user run it (default: `viewAny` on the model) |
| `static fromRequest(Request $request): static` | Builds it for `POST /exports` |

# WritesFiles

`trait Concerns\WritesFiles`, for any `Exportable`.

| Method | |
| --- | --- |
| `writeTo(string $path, Formats\|string\|null $format = null): int` | Rows written |
| `download(?string $filename = null, Formats\|string\|null $format = null): BinaryFileResponse` | |
| `store(?string $path = null, ?string $disk = null, Formats\|string\|null $format = null): string` | Path on the disk |
| `queue(?Model $user = null, Formats\|string\|null $format = null, ?string $filename = null, ?string $disk = null): Models\Export` | |
| `downloadName(?string $filename, Formats $format): string` | The file name with extension |

# Other exports and contracts

| Class | |
| --- | --- |
| `IterableExport::make(array $headings, iterable\|Closure $rows, string $filename = 'export', ?int $total_rows = null)` | Rows that aren't models |
| `Contracts\Exportable` | `headings()`, `rows()`, `totalRows()`, `filename()`, `label()` |
| `Contracts\HasExportHeadings` | `exportHeadings(): array` on a model |
| `Columns\Column` | `make()`, `label()`, `format()`, `value()`, `getLabel()`, `getFormatter()`, `getValueResolver()`, `relationPath()`, `attribute()` |

# Exports

`class Exports` (static).

| Method | |
| --- | --- |
| `register(string\|array $type, ?string $export_class = null): void` | Lets the API start an export by name |
| `registered(): array` | `type => class` |
| `exportClass(string $type): ?string` | |
| `typeOf(Exportable\|string $export): ?string` | |
| `forget(?string $type = null): void` | |
| `exportModel(): string`, `newExportModel(): Export` | `exports.models.export` |
| `ignoreMigrations(): void` | |

# Models\Export

| Method | |
| --- | --- |
| `user(): MorphTo` | |
| `scopeOwnedBy(Model $user)`, `scopeStatus(ExportStatuses\|string $status)`, `scopeExpired(?DateTimeInterface $now = null)` | |
| `isOwnedBy(?Model $user): bool` | |
| `progress(): ?int` | 0–100 |
| `hasExpired()`, `isDownloadable()`, `isCancellable()`, `wasCancelled()`, `fileExists()`: `bool` | |
| `cancel(): bool` | |
| `markProcessing(?int $total_rows)`, `recordProgress(int $processed_rows)`, `markCompleted(string $disk, string $path, string $filename, int $rows, ?int $size)`, `markFailed(Throwable\|string $error)` | Used by the job |
| `download(): StreamedResponse` | |
| `deleteFile(): void` | |
| `temporaryDownloadUrl(?DateTimeInterface $expires_at = null): ?string` | |
| `broadcastChannel(): string`, `ownerBroadcastChannel(): ?string` | |
| `static expiresAfterDays(): int` | |

# Enums

| Enum | Cases | Methods |
| --- | --- | --- |
| `Enums\Formats` | `CSV`, `XLSX`, `ODS` | `extension()`, `mimeType()`, `label()`, `static resolve($format, $filename)`, `static enabled()` |
| `Enums\ExportStatuses` | `PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`, `CANCELLED` | `label()`, `isFinished()`, `isCancellable()` |

# Jobs, events, notifications, commands

| Class | |
| --- | --- |
| `Jobs\ProcessExport` | `__construct(Export $export, Exportable $exportable)`, `handle()`, `failed()`; `$tries = 1`, `$timeout`, `$locale` |
| `Events\ExportStarted`, `ExportProgressed`, `ExportCompleted`, `ExportFailed`, `ExportCancelled` | `public Export $export`; `ShouldBroadcastNow` with `broadcastWhen()` |
| `Notifications\ExportCompletedNotification`, `ExportFailedNotification` | `mail` and `database` (`toArray()`) |
| `Commands\PruneExportsCommand` | `exports:prune {--days=} {--pretend}` |
| `Exceptions\ExportCancelledException` | Thrown inside the job to stop it |

# HTTP

| Class | |
| --- | --- |
| `Routing\ExportRoutes` | `register(Router $router, array $options)`, `guard(Request)`, `namePrefix(Request)`; `Route::exports(array $options = [])` |
| `Http\Controllers\ExportsController` | `index`, `store`, `show`, `download`, `signedDownload`, `cancel`, `destroy` |
| `Http\Requests\ExportRequest` | `type`, `format`, `filename` |
| `Http\Resources\ExportResource` | |
| `Policies\ExportPolicy` | `viewAny`, `viewOthers`, `create`, `view`, `download`, `cancel`, `delete` |
| `Views\ViewRenderer` | `render(string $page, array $data)`, `stack()`, `isInertia()`, `inertiaPage(string $page)` |

# Support

| Class | |
| --- | --- |
| `Writers\SpreadsheetWriter` | `write(string $path, Formats $format, array $headings, iterable $rows): int` |
| `Support\SerializableQuery` | `static from(Builder\|Relation $query)`, `toBuilder(): Builder` |
| `Support\Broadcasting` | `enabled()`, `pollInterval()`, `channelPrefix()`, `registerChannels()`, `encodeType()`, `payload()` |
| `Support\TemporaryFile` | `path(Formats $format)`, `moveToDisk(…)`, `delete(string $path)` |
