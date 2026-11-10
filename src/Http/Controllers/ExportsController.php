<?php

namespace Javaabu\Exports\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Http\Requests\ExportRequest;
use Javaabu\Exports\Http\Resources\ExportResource;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Routing\ExportRoutes;
use Javaabu\Exports\Support\Broadcasting;
use Javaabu\Exports\Views\ViewRenderer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * List, start, follow, download, cancel and delete exports
 *
 * Every action answers JSON when the request expects it, and a Blade or Inertia page
 * otherwise. Override it with `config('exports.controllers.exports')`, the `controller`
 * option of `Route::exports()`, or by binding a subclass in the container.
 */
class ExportsController extends Controller
{
    public function __construct(
        protected ViewRenderer $views,
    ) {}

    public function index(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', Exports::exportModel());

        $user = $request->user();
        $sees_others = $user->can('viewOthers', Exports::exportModel());
        $per_page = min(max($request->integer('per_page', 20), 1), 100);

        $query = $this->query($request)
            ->with('user')
            ->latest('id');

        if (! $sees_others || $request->boolean('mine')) {
            $query->ownedBy($user);
        }

        if ($status = ExportStatuses::tryFrom((string) $request->query('status'))) {
            $query->status($status);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        $exports = $query->paginate($per_page)->withQueryString();

        if ($request->expectsJson()) {
            return ExportResource::collection($exports)
                ->additional(['meta' => $this->meta($request)])
                ->response();
        }

        return $this->views->render('exports.index', [
            'exports' => $this->views->isInertia()
                ? ExportResource::collection($exports)
                : $exports,
            'available' => $this->availableExports($request),
            'formats' => $this->formats(),
            'sees_others' => $sees_others,
            'filters' => [
                'status' => $status?->value,
                'type' => $type ?: null,
                'mine' => $request->boolean('mine'),
            ],
            'statuses' => collect(ExportStatuses::cases())
                ->map(fn (ExportStatuses $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            ...$this->meta($request),
            'strings' => $this->views->isInertia() ? $this->strings() : null,
            'title' => __('Exports'),
        ]);
    }

    public function store(ExportRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility($request, 'create', Exports::exportModel());

        $exportable = $this->makeExportable($request, $request->validated('type'));

        abort_unless($this->mayRun($exportable, $request->user()), 403);

        $export = $exportable->queue(
            $request->user(),
            $request->validated('format'),
            $this->safeFilename($request->validated('filename')),
        );

        if ($request->expectsJson()) {
            return (new ExportResource($export->refresh()))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route($this->routeName($request, 'index'))
            ->with('success', __('Your export has started. We will let you know when it is ready.'));
    }

    public function show(Request $request, string $export): View|Responsable|JsonResponse
    {
        $export = $this->findExport($request, $export);

        $this->authorizeAbility($request, 'view', $export);

        if ($request->expectsJson()) {
            return (new ExportResource($export->load('user')))
                ->additional(['meta' => $this->meta($request)])
                ->response();
        }

        return $this->views->render('exports.show', [
            'export' => $this->views->isInertia()
                ? (new ExportResource($export->load('user')))->resolve($request)
                : $export,
            ...$this->meta($request),
            'strings' => $this->views->isInertia() ? $this->strings() : null,
            'title' => $export->name,
        ]);
    }

    public function download(Request $request, string $export): StreamedResponse|JsonResponse
    {
        $export = $this->findExport($request, $export);

        $this->authorizeAbility($request, 'download', $export);

        return $this->downloadResponse($export);
    }

    /**
     * The link in notifications: signed and expiring, no login needed
     */
    public function signedDownload(Request $request, string $export): StreamedResponse|JsonResponse
    {
        return $this->downloadResponse($this->findExport($request, $export));
    }

    public function cancel(Request $request, string $export): RedirectResponse|JsonResponse
    {
        $export = $this->findExport($request, $export);

        $this->authorizeAbility($request, 'cancel', $export);

        if (! $export->cancel()) {
            return $this->failure($request, __('This export can no longer be cancelled.'), 409);
        }

        if ($request->expectsJson()) {
            return (new ExportResource($export))->response();
        }

        return back()->with('success', __('Export cancelled'));
    }

    public function destroy(Request $request, string $export): RedirectResponse|JsonResponse
    {
        $export = $this->findExport($request, $export);

        $this->authorizeAbility($request, 'delete', $export);

        $export->cancel();
        $export->delete();

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()
            ->route($this->routeName($request, 'index'))
            ->with('success', __('Export deleted'));
    }

    protected function downloadResponse(Export $export): StreamedResponse|JsonResponse
    {
        abort_unless($export->status === ExportStatuses::COMPLETED, 404, __('This export is not ready yet.'));

        abort_if($export->hasExpired() || ! $export->fileExists(), 410, __('This export has expired.'));

        return $export->download();
    }

    protected function query(Request $request): Builder
    {
        return Exports::exportModel()::query();
    }

    protected function findExport(Request $request, string $uuid): Export
    {
        return $this->query($request)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * The registered export for a type, made from the request when it has fromRequest()
     */
    protected function makeExportable(Request $request, string $type): Exportable
    {
        $class = Exports::exportClass($type);

        abort_unless($class, 404);

        if (method_exists($class, 'fromRequest')) {
            return $class::fromRequest($request);
        }

        return new $class;
    }

    protected function mayRun(Exportable $exportable, ?Model $user): bool
    {
        if (! method_exists($exportable, 'authorize')) {
            return true;
        }

        return (bool) $exportable->authorize($user);
    }

    /**
     * The registered exports the user may start
     *
     * @return array<int, array{type: string, label: string}>
     */
    protected function availableExports(Request $request): array
    {
        $available = [];

        foreach (Exports::registered() as $type => $class) {
            $exportable = new $class;

            if (! $this->mayRun($exportable, $request->user())) {
                continue;
            }

            $available[] = ['type' => $type, 'label' => $exportable->label()];
        }

        return $available;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function formats(): array
    {
        return array_map(
            fn (Formats $format) => ['value' => $format->value, 'label' => $format->label()],
            Formats::enabled(),
        );
    }

    /**
     * What a page needs to follow progress: broadcasting or polling, and the route names
     *
     * @return array<string, mixed>
     */
    protected function meta(Request $request): array
    {
        return [
            'broadcasting' => Broadcasting::enabled(),
            'poll_interval' => Broadcasting::pollInterval(),
            'routes' => $this->routeNames($request),
            'urls' => [
                'index' => route($this->routeName($request, 'index')),
                'store' => route($this->routeName($request, 'store')),
            ],
        ];
    }

    /**
     * The translated text of the pages, for the Inertia pages (Blade views use __() directly)
     *
     * @return array<string, string>
     */
    protected function strings(): array
    {
        return [
            'title' => __('Exports'),
            'new_export' => __('New export'),
            'export' => __('Export'),
            'format' => __('Format'),
            'start' => __('Start export'),
            'status' => __('Status'),
            'all_statuses' => __('All statuses'),
            'only_mine' => __('Only my exports'),
            'filter' => __('Filter'),
            'name' => __('Name'),
            'progress' => __('Progress'),
            'rows' => __('Rows'),
            'started_by' => __('Started by'),
            'created' => __('Created'),
            'expires' => __('Expires'),
            'actions' => __('Actions'),
            'download' => __('Download'),
            'cancel' => __('Cancel'),
            'delete' => __('Delete'),
            'confirm_cancel' => __('Cancel this export?'),
            'confirm_delete' => __('Delete this export and its file?'),
            'no_exports' => __('No exports yet'),
            'no_exports_message' => __('Exports you start will appear here.'),
            'rows_of' => __(':processed of :total rows'),
            'rows_written' => __(':count rows'),
            'error' => __('Error'),
            'back' => __('Back to exports'),
            'file' => __('File'),
            'size' => __('Size'),
        ];
    }

    /**
     * A user-supplied file name without paths or characters file systems reject
     */
    protected function safeFilename(?string $filename): ?string
    {
        if ($filename === null) {
            return null;
        }

        $filename = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', $filename), " .-\t");

        return $filename === '' ? null : $filename;
    }

    protected function failure(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('error', $message);
    }

    protected function authorizeAbility(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        abort_unless($user && $user->can($ability, $arguments), 403);
    }

    protected function routeName(Request $request, string $action): string
    {
        return ExportRoutes::namePrefix($request) . ".{$action}";
    }

    /**
     * Names of the routes, honouring the prefix the app registered them under
     *
     * @return array<string, string>
     */
    protected function routeNames(Request $request): array
    {
        return collect(['index', 'store', 'show', 'download', 'cancel', 'destroy'])
            ->mapWithKeys(fn (string $action) => [$action => $this->routeName($request, $action)])
            ->all();
    }
}
