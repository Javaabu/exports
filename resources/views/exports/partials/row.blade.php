<x-forms::table.row
    name="exports"
    :model-id="$export->uuid"
    no-checkbox
    data-export-row="{{ $export->uuid }}"
    data-export-url="{{ route($routes['show'], $export->uuid) }}"
    data-export-channel="{{ $broadcasting ? $export->broadcastChannel() : '' }}"
    data-export-finished="{{ $export->status->isFinished() ? 'true' : 'false' }}">
    <x-forms::table.cell name="name" :label="__('Name')">
        <a href="{{ route($routes['show'], $export->uuid) }}" class="font-medium hover:underline">{{ $export->name }}</a>
        <div class="text-sm text-muted-foreground" dir="auto">{{ $export->filename }}</div>
    </x-forms::table.cell>
    <x-forms::table.cell name="format" :label="__('Format')">{{ $export->format->label() }}</x-forms::table.cell>
    <x-forms::table.cell name="status" :label="__('Status')">
        <span data-export-status>
            <x-forms::status :color="$export->status->color()" :label="$export->status->label()" />
        </span>
    </x-forms::table.cell>
    <x-forms::table.cell name="progress" :label="__('Progress')">
        @include('exports::exports.partials.progress', ['export' => $export])
    </x-forms::table.cell>
    <x-forms::table.cell name="rows" :label="__('Rows')">
        <span class="tabular-nums" data-export-rows>{{ $export->total_rows === null ? '' : number_format($export->processed_rows) . ' / ' . number_format($export->total_rows) }}</span>
    </x-forms::table.cell>
    @if($sees_others)
        <x-forms::table.cell name="user" :label="__('Started by')">{{ $export->user?->getAttribute('name') }}</x-forms::table.cell>
    @endif
    <x-forms::table.cell name="created_at" :label="__('Created')">
        <time datetime="{{ $export->created_at->toIso8601String() }}">{{ $export->created_at->diffForHumans() }}</time>
    </x-forms::table.cell>
    <x-forms::table.cell name="actions" :label="__('Actions')">
        @include('exports::exports.partials.actions', ['export' => $export])
    </x-forms::table.cell>
</x-forms::table.row>
