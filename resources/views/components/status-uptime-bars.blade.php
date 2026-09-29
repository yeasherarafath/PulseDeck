@props(['days' => []])
@php
    /** @var \App\Services\Status\PublicStatusService $helper */
    $helper = app(\App\Services\Status\PublicStatusService::class);
@endphp
<div class="uptime-bars-scroll" role="img" aria-label="Daily uptime history">
<div class="d-flex gap-1">
    @foreach ($days as $day)
        @php($color = $helper->barColor($day['uptime']))
        <div class="rounded {{ $color }}" style="width: 100%; height: 34px; min-width: 3px;"
            title="{{ $day['date'] }} — {{ $day['uptime'] === null ? 'no data' : number_format($day['uptime'], 2).'% · '.$day['successful'].'/'.$day['total'].' checks' }}"></div>
    @endforeach
</div>
</div>
