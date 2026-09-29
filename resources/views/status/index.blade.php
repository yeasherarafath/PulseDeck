@extends('layouts.public-status')

@section('meta-title', config('app.name', 'Status').' Status')
@section('meta-description', 'Live service status, incident history, and uptime.')

@section('content')
    <div data-status-poll data-refresh-url="{{ route('status.refresh') }}" data-refresh-seconds="{{ $refresh_seconds }}">
        {{-- Overall banner --}}
        <div class="card card-lg mb-3" id="overall-banner">
            <div class="card-body text-center">
                <div id="overall-dot">
                    @if ($status === 'operational')
                        <span class="status-dot status-dot-animated bg-green mb-3" style="width: 1rem; height: 1rem;"></span>
                    @elseif ($status === 'maintenance')
                        <span class="status-dot bg-blue mb-3" style="width: 1rem; height: 1rem;"></span>
                    @elseif ($status === 'degraded')
                        <span class="status-dot bg-yellow mb-3" style="width: 1rem; height: 1rem;"></span>
                    @else
                        <span class="status-dot status-dot-animated bg-red mb-3" style="width: 1rem; height: 1rem;"></span>
                    @endif
                </div>
                <h1 class="card-title h2" id="overall-label">{{ $status_label }}</h1>
                <p class="text-secondary mb-0">Last updated <span id="overall-updated">{{ $updated_human }}</span></p>
                <p class="text-secondary small mb-0">Server time: <span id="server-time">{{ $server_time }}</span></p>
            </div>
        </div>

        {{-- Active incidents --}}
        @if ($incidents !== [])
            <div class="card mb-3 border-red" id="active-incidents">
                <div class="card-header">
                    <h3 class="card-title">Active incidents</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($incidents as $incident)
                        <a href="{{ route('status.incidents.show', $incident['slug']) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="svc-row-name">
                                    <strong>{{ $incident['title'] }}</strong>
                                    <div class="text-secondary small">{{ $incident['service_name'] ?? 'Multiple services' }} &middot; started {{ $incident['started_human'] }}</div>
                                </div>
                                <span class="svc-badge"><x-status-badge :status="$incident['status']" /></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Scheduled maintenance --}}
        @if ($maintenances !== [])
            <div class="card mb-3 border-blue">
                <div class="card-header">
                    <h3 class="card-title">Scheduled maintenance</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($maintenances as $maintenance)
                        <div class="list-group-item">
                            <strong>{{ $maintenance['title'] }}</strong>
                            <div class="text-secondary small">
                                {{ $maintenance['starts'] }} – {{ $maintenance['ends'] }}
                                &middot; <x-status-badge :status="$maintenance['status']" />
                            </div>
                            @if ($maintenance['description'])
                                <div class="mt-1">{{ $maintenance['description'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Service groups --}}
        @forelse ($groups as $group)
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">{{ $group['name'] }}</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($group['services'] as $service)
                        <a href="{{ route('status.services.show', $service['slug']) }}" class="list-group-item list-group-item-action" data-service-row="{{ $service['slug'] }}">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="svc-row-name">
                                    <strong>{{ $service['name'] }}</strong>
                                    <div class="text-secondary small svc-response">
                                        {{ $service['last_checked_human'] ? 'Checked '.$service['last_checked_human'] : 'Not checked yet' }}
                                    </div>
                                </div>
                                <span class="svc-badge"><x-status-badge :status="$service['status']" /></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body">
                    <div class="empty">
                        <p class="empty-title">No public services yet</p>
                        <p class="empty-subtitle text-secondary">Services marked public will appear here.</p>
                    </div>
                </div>
            </div>
        @endforelse

        {{-- 90-day history --}}
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Past {{ $window_days }} days</h3>
            </div>
            <div class="card-body">
                @foreach ($groups as $group)
                    @foreach ($group['services'] as $service)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $service['name'] }}</span>
                                @php($bars = $uptime[$service['id']] ?? [])
                                @php($known = collect($bars)->filter(fn ($b) => $b['uptime'] !== null))
                                <span class="text-secondary">{{ $known->isNotEmpty() ? number_format($known->avg('uptime'), 2).'%' : 'no data' }}</span>
                            </div>
                            <x-status-uptime-bars :days="$bars" />
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Subscribe --}}
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Get incident updates by email</h3>
                @if (session('status'))
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('status.subscribe') }}">
                    @csrf
                    <div class="input-group">
                        <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" placeholder="you@example.com" required />
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </div>
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    </div>
@endsection
