@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Dashboard')

@section('page-actions')
    @can('status.services.create')
        <a href="{{ route('admin.status.services.create') }}" class="btn btn-primary">New service</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="row row-deck row-cards">
        @foreach (['Total Services' => $counts['total'], 'Operational' => $counts['operational'], 'Degraded' => $counts['degraded'], 'Outage' => $counts['outage'], 'Maintenance' => $counts['maintenance']] as $label => $value)
            <div class="col-sm-6 col-lg-4 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ $label }}</div>
                        <div class="h1 mb-0">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Monitoring engine</h3>
                </div>
                <div class="card-body">
                    @if ($heartbeat && $heartbeat > now()->subMinutes(5)->timestamp)
                        <div class="d-flex align-items-center">
                            <span class="status-dot status-dot-animated bg-green me-2"></span>
                            <div>
                                <div>Running</div>
                                <div class="text-secondary small">Last heartbeat {{ \Carbon\Carbon::createFromTimestamp($heartbeat)->diffForHumans() }}</div>
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center">
                            <span class="status-dot bg-red me-2"></span>
                            <div>
                                <div>Not running</div>
                                <div class="text-secondary small">No heartbeat in the last 5 minutes — start the scheduler and queue worker.</div>
                            </div>
                        </div>
                    @endif
                    <div class="mt-3 text-secondary small">
                        Avg response (24h): {{ $avgResponse !== null ? round($avgResponse).' ms' : '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Current incidents</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <tbody>
                            @forelse ($incidents as $incident)
                                <tr>
                                    <td>
                                        <div>{{ $incident->title }}</div>
                                        <div class="text-secondary small">{{ $incident->service?->name ?? '—' }} &middot; {{ $incident->started_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="text-nowrap text-end"><x-status-badge :status="$incident->status" /></td>
                                </tr>
                            @empty
                                <tr><td class="text-secondary">No active incidents.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Services with problems</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <tbody>
                            @forelse ($problems as $service)
                                <tr>
                                    <td><a href="{{ route('admin.status.services.show', $service) }}">{{ $service->name }}</a></td>
                                    <td class="text-nowrap text-end"><x-status-badge :status="$service->current_status" /></td>
                                </tr>
                            @empty
                                <tr><td class="text-secondary">Everything looks good.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent checks</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <tbody>
                            @forelse ($recentChecks as $check)
                                <tr>
                                    <td>
                                        <div>{{ $check->service?->name ?? '—' }}</div>
                                        <div class="text-secondary small">{{ $check->checked_at->diffForHumans() }} &middot; {{ $check->http_status ?? 'no response' }}{{ $check->response_time !== null ? ' · '.$check->response_time.' ms' : '' }}</div>
                                    </td>
                                    <td class="text-nowrap text-end"><x-status-badge :status="$check->status" /></td>
                                </tr>
                            @empty
                                <tr><td class="text-secondary">No checks recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
