@php($progress = $export->progress())
<div class="flex items-center gap-2 min-w-32" data-export-progress>
    <div class="h-2 w-full overflow-hidden rounded-full bg-muted"
         role="progressbar"
         aria-label="{{ __('Progress') }}"
         aria-valuemin="0"
         aria-valuemax="100"
         @if($progress !== null) aria-valuenow="{{ $progress }}" @endif>
        <div class="h-full rounded-full bg-primary transition-[inline-size] {{ $progress === null && ! $export->status->isFinished() ? 'animate-pulse' : '' }}"
             style="inline-size: {{ $progress ?? ($export->status->isFinished() ? 0 : 33) }}%"
             data-export-progress-bar></div>
    </div>
    <span class="text-sm tabular-nums text-muted-foreground" data-export-progress-text>{{ $progress === null ? '' : "{$progress}%" }}</span>
</div>
