@extends(config('exports.layout', 'layouts.app'))

@section(config('exports.layout_section', 'content'))
    @manifestRequire('exports::progress')

    <div class="flex flex-col gap-6"
         data-exports
         data-broadcasting="{{ $broadcasting ? 'true' : 'false' }}"
         data-poll-interval="{{ $poll_interval }}">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <x-forms::link-button :url="route($routes['index'])" color="secondary" size="sm" start-icon="chevron-left">
                {{ __('Back to exports') }}
            </x-forms::link-button>
        </div>

        @if(session('success'))
            <x-forms::alert type="success">{{ session('success') }}</x-forms::alert>
        @endif

        @if(session('error'))
            <x-forms::alert type="danger">{{ session('error') }}</x-forms::alert>
        @endif

        <x-forms::card
            data-export-row="{{ $export->uuid }}"
            data-export-url="{{ route($routes['show'], $export->uuid) }}"
            data-export-channel="{{ $broadcasting ? $export->broadcastChannel() : '' }}"
            data-export-finished="{{ $export->status->isFinished() ? 'true' : 'false' }}">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('Status') }}</dt>
                    <dd data-export-status><x-forms::status :color="$export->status->color()" :label="$export->status->label()" /></dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('Progress') }}</dt>
                    <dd>@include('exports::exports.partials.progress', ['export' => $export])</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('Rows') }}</dt>
                    <dd class="tabular-nums" data-export-rows>{{ $export->total_rows === null ? '' : number_format($export->processed_rows) . ' / ' . number_format($export->total_rows) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('Format') }}</dt>
                    <dd>{{ $export->format->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('File') }}</dt>
                    <dd dir="auto">{{ $export->filename }}</dd>
                </div>
                @if($export->size)
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ __('Size') }}</dt>
                        <dd>{{ \Illuminate\Support\Number::fileSize($export->size) }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('Created') }}</dt>
                    <dd><time datetime="{{ $export->created_at->toIso8601String() }}">{{ $export->created_at->isoFormat('LLL') }}</time></dd>
                </div>
                @if($export->expires_at)
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ __('Expires') }}</dt>
                        <dd><time datetime="{{ $export->expires_at->toIso8601String() }}">{{ $export->expires_at->isoFormat('LLL') }}</time></dd>
                    </div>
                @endif
                @if($export->error)
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-muted-foreground">{{ __('Error') }}</dt>
                        <dd class="text-destructive">{{ $export->error }}</dd>
                    </div>
                @endif
            </dl>

            <x-slot:footer>
                @include('exports::exports.partials.actions', ['export' => $export])
            </x-slot:footer>
        </x-forms::card>
    </div>
@endsection
