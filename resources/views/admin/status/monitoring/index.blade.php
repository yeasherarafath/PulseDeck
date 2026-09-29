@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Monitoring')

@section('content')
    <div class="row row-deck row-cards">
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Queued checks</div>
                    <div class="h1 mb-0">{{ $queuedJobs }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Failed jobs</div>
                    <div class="h1 mb-0">{{ $failedJobs }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Engine heartbeat</div>
                    <div class="h1 mb-0">
                        @if ($heartbeat && $heartbeat > now()->subMinutes(5)->timestamp)
                            <span class="text-green">Running</span>
                        @else
                            <span class="text-red">Stale</span>
                        @endif
                    </div>
                    <div class="text-secondary small">
                        {{ $heartbeat ? \Carbon\Carbon::createFromTimestamp($heartbeat)->diffForHumans() : 'never' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="w-1">Status</th>
                                <th>Last check</th>
                                <th>Next check</th>
                                <th>Response</th>
                                <th>Last error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                @php($latest = $service->checks->first())
                                <tr>
                                    <td><a href="{{ route('admin.status.services.show', $service) }}">{{ $service->name }}</a></td>
                                    <td class="text-nowrap"><x-status-badge :status="$service->current_status" /></td>
                                    <td class="text-nowrap">{{ $service->last_checked_at?->diffForHumans() ?? 'never' }}</td>
                                    <td class="text-nowrap">{{ $service->next_check_at?->diffForHumans() ?? '—' }}</td>
                                    <td>{{ $latest?->response_time !== null ? $latest->response_time.' ms' : '—' }}</td>
                                    <td class="text-secondary">{{ \Illuminate\Support\Str::limit($latest?->error_message ?? '', 80) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-secondary">No services configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
