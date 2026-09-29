@extends('layouts.public-status')

@section('meta-title', $service['name'].' status')
@section('meta-description', 'Current status, uptime history, and response times for '.$service['name'].'.')

@section('content')
    <div class="card card-lg mb-3">
        <div class="card-body">
            @if ($group_name)
                <div class="page-pretitle">{{ $group_name }}</div>
            @endif
            <h1 class="card-title h2">{{ $service['name'] }}</h1>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <x-status-badge :status="$service['status']" />
                <span class="text-secondary">{{ number_format($uptime_90['uptime'], 2) }}% uptime ({{ $window_days }}d)</span>
                @if ($avg_response !== null)
                    <span class="text-secondary">Avg {{ round($avg_response) }} ms</span>
                @endif
                <span class="text-secondary">{{ $service['last_checked_human'] ? 'Checked '.$service['last_checked_human'] : 'Not checked yet' }}</span>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ $window_days }}-day uptime</h3>
        </div>
        <div class="card-body">
            @if ($daily === [])
                <p class="text-secondary mb-0">No history recorded yet — check back after the first monitoring runs.</p>
            @else
                <x-status-uptime-bars :days="$daily" />
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">Response time</h3>
        </div>
        <div class="card-body">
            @if ($response_times === [])
                <p class="text-secondary mb-0">No response-time samples yet.</p>
            @else
                <div id="response-chart" data-samples='@json($response_times)'></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent incidents</h3>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($incidents as $incident)
                <a href="{{ route('status.incidents.show', $incident['slug']) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="svc-row-name">
                            <strong>{{ $incident['title'] }}</strong>
                            <div class="text-secondary small">{{ $incident['started'] }}{{ $incident['resolved'] ? ' – resolved '.$incident['resolved'] : '' }}</div>
                        </div>
                        <span class="svc-badge"><x-status-badge :status="$incident['status']" /></span>
                    </div>
                </a>
            @empty
                <div class="list-group-item text-secondary">No incidents recorded for this service.</div>
            @endforelse
        </div>
    </div>
@endsection
