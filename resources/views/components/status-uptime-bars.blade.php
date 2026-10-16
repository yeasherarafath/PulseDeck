@props(['days' => [], 'serviceSlug' => null])
@php
    /** @var \App\Services\Status\PublicStatusService $helper */
    $helper = app(\App\Services\Status\PublicStatusService::class);
    $linkable = $serviceSlug !== null;
@endphp
<div class="uptime-bars-scroll"@if (! $linkable) role="img" aria-label="Daily uptime history"@endif>
<div class="d-flex gap-1">
    @foreach ($days as $day)
        @php($color = $helper->barColor($day['uptime']))
        @php($failed = max(0, ($day['total'] ?? 0) - ($day['successful'] ?? 0)))
        @php($title = $day['date'].' — '.($day['uptime'] === null ? 'no data' : number_format($day['uptime'], 2).'% · '.$day['successful'].'/'.$day['total'].' checks'))
        @if ($linkable && $day['uptime'] !== null && $failed > 0)
            <a class="rounded {{ $color }}" style="width: 100%; height: 34px; min-width: 3px;"
                title="{{ $title }}"
                aria-label="{{ $day['date'] }}: {{ $failed }} of {{ $day['total'] }} checks failed — view issues"
                href="{{ route('status.services.show', ['service' => $serviceSlug, 'date' => $day['date']]) }}"></a>
        @else
            <div class="rounded {{ $color }}" style="width: 100%; height: 34px; min-width: 3px;"
                title="{{ $title }}"></div>
        @endif
    @endforeach
</div>
</div>
