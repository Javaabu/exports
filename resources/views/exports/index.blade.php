@extends(config('exports.layout', 'layouts.app'))

@section(config('exports.layout_section', 'content'))
    @manifestRequire('exports::progress')

    <div class="flex flex-col gap-6"
         data-exports
         data-broadcasting="{{ $broadcasting ? 'true' : 'false' }}"
         data-poll-interval="{{ $poll_interval }}">
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        @if(session('success'))
            <x-forms::alert type="success">{{ session('success') }}</x-forms::alert>
        @endif

        @if(session('error'))
            <x-forms::alert type="danger">{{ session('error') }}</x-forms::alert>
        @endif

        @if($available)
            <x-forms::card :title="__('New export')">
                <x-forms::form method="POST" :action="route($routes['store'])" class="flex flex-wrap items-end gap-4">
                    <x-forms::select
                        name="type"
                        :label="__('Export')"
                        :options="collect($available)->pluck('label', 'type')->all()"
                        required />
                    <x-forms::select
                        name="format"
                        :label="__('Format')"
                        :options="collect($formats)->pluck('label', 'value')->all()"
                        :default="config('exports.format')" />
                    <x-forms::submit>{{ __('Start export') }}</x-forms::submit>
                </x-forms::form>
            </x-forms::card>
        @endif

        <x-forms::form method="GET" :action="route($routes['index'])" class="flex flex-wrap items-end gap-4" data-exports-filter>
            <x-forms::select
                name="status"
                :label="__('Status')"
                :placeholder="__('All statuses')"
                show-placeholder
                :options="collect($statuses)->pluck('label', 'value')->all()"
                :default="$filters['status']" />
            @if($sees_others)
                <x-forms::checkbox name="mine" value="1" :label="__('Only my exports')" :default="$filters['mine']" />
            @endif
            <x-forms::submit color="secondary" :animate="false">{{ __('Filter') }}</x-forms::submit>
        </x-forms::form>

        <x-forms::table model="exports" no-checkbox>
            <x-slot:headers>
                <x-forms::table.heading :label="__('Name')" />
                <x-forms::table.heading :label="__('Format')" />
                <x-forms::table.heading :label="__('Status')" />
                <x-forms::table.heading :label="__('Progress')" />
                <x-forms::table.heading :label="__('Rows')" />
                @if($sees_others)
                    <x-forms::table.heading :label="__('Started by')" />
                @endif
                <x-forms::table.heading :label="__('Created')" />
                <x-forms::table.heading :label="__('Actions')" />
            </x-slot:headers>

            <x-slot:rows>
                @forelse($exports as $export)
                    @include('exports::exports.partials.row', ['export' => $export])
                @empty
                    <tr data-slot="table-row">
                        <td colspan="{{ $sees_others ? 8 : 7 }}" class="p-6 text-center text-muted-foreground">
                            <p class="font-medium">{{ __('No exports yet') }}</p>
                            <p class="text-sm">{{ __('Exports you start will appear here.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </x-slot:rows>
        </x-forms::table>

        @if($exports->hasPages())
            <x-forms::pagination :paginator="$exports" />
        @endif
    </div>
@endsection
