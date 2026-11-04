@php($user = auth()->user())
<div class="flex flex-wrap items-center gap-2" data-export-actions>
    @if($export->isDownloadable() && $user?->can('download', $export))
        <x-forms::link-button :url="route($routes['download'], $export->uuid)" size="sm" start-icon="download">
            {{ __('Download') }}
        </x-forms::link-button>
    @endif

    @if($export->isCancellable() && $user?->can('cancel', $export))
        <x-forms::form method="POST" :action="route($routes['cancel'], $export->uuid)" data-confirm="{{ __('Cancel this export?') }}">
            <x-forms::submit color="secondary" size="sm" :animate="false">{{ __('Cancel') }}</x-forms::submit>
        </x-forms::form>
    @endif

    @if($user?->can('delete', $export))
        <x-forms::form method="DELETE" :action="route($routes['destroy'], $export->uuid)" data-confirm="{{ __('Delete this export and its file?') }}">
            <x-forms::submit color="danger" size="sm" :animate="false">{{ __('Delete') }}</x-forms::submit>
        </x-forms::form>
    @endif
</div>
