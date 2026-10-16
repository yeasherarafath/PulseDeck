@extends('layouts.public-status')

@section('meta-title', $service['name'].' status | '.setting('app_name', config('app.name', 'Status')))
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
            @if (collect($daily)->contains(fn ($day) => $day['uptime'] !== null))
                <x-status-uptime-bars :days="$daily" :service-slug="$service['slug']" />
                <p class="text-secondary small mt-2 mb-0">Select a bar with failures to see what went wrong that day.</p>
            @else
                <p class="text-secondary mb-0">No history recorded yet — check back after the first monitoring runs.</p>
            @endif
        </div>
    </div>

    @if ($day_detail !== null)
        <div class="card mb-3" id="day-issues">
            <div class="card-header">
                <h3 class="card-title">Issues on {{ $day_detail['date'] }}</h3>
            </div>
            @if (! $day_detail['has_data'])
                <div class="card-body">
                    <p class="text-secondary mb-0">No data recorded for this date.</p>
                </div>
            @else
                <div class="card-body">
                    <p class="text-secondary mb-0">
                        {{ $day_detail['failed'] }} failed of {{ $day_detail['total'] }} checks
                        · <a href="{{ route('status.services.show', $service['slug']) }}">show full {{ $window_days }}-day history</a>
                    </p>
                </div>
                @if ($day_detail['checks'] === [])
                    <div class="card-body">
                        @if ($day_detail['records_expired'])
                            <p class="text-secondary mb-0">Detailed check records for this date have expired; only the daily aggregate remains.</p>
                        @else
                            <p class="text-secondary mb-0">No failed checks recorded for this date.</p>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th class="w-1">Result</th>
                                    <th>HTTP</th>
                                    <th>Response</th>
                                    <th>Error / assertions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($day_detail['checks'] as $check)
                                    <tr>
                                        <td class="text-nowrap">{{ $check['at'] }}</td>
                                        <td class="text-nowrap"><x-status-badge :status="$check['status']" /></td>
                                        <td>{{ $check['http'] ?? '—' }}</td>
                                        <td>{{ $check['ms'] !== null ? $check['ms'].' ms' : '—' }}</td>
                                        <td>
                                            @if ($check['error'])
                                                <div>{{ $check['error'] }}</div>
                                            @endif
                                            @if ($check['message'])
                                                <div class="text-danger">{{ $check['message'] }}</div>
                                            @endif
                                            @if ($check['assertions'] !== [])
                                                <details>
                                                    <summary class="cursor-pointer text-secondary">{{ count($check['assertions']) }} failed assertion(s)</summary>
                                                    <ul class="mb-0 ps-3 small">
                                                        @foreach ($check['assertions'] as $failure)
                                                            <li><strong>{{ $failure['assertion'] }}:</strong> {{ $failure['message'] }}</li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            @elseif (! $check['error'] && ! $check['message'])
                                                <span class="text-secondary">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($day_detail['truncated'])
                        <div class="card-footer text-secondary">Showing {{ count($day_detail['checks']) }} of {{ $day_detail['failed'] }} failed checks.</div>
                    @endif
                @endif
            @endif
        </div>
    @endif

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
