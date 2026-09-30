@extends('layouts.admin-tabler')

@section('page-pretitle', 'Services')
@section('page-title', $service->name)

@section('page-actions')
    <div class="btn-list">
        @can('status.monitoring.run')
            <form method="POST" action="{{ route('admin.status.services.check', $service) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn">Check now</button>
            </form>
            <button type="button" class="btn" id="test-saved-btn" data-bs-toggle="modal" data-bs-target="#test-saved-modal" data-url="{{ route('admin.status.services.test', $service) }}">Test request</button>
        @endcan
        @can('status.services.update')
            <a href="{{ route('admin.status.services.edit', $service) }}" class="btn btn-primary">Edit</a>
        @endcan
        @can('status.incidents.create')
            <a href="{{ route('admin.status.incidents.create', ['service' => $service->id]) }}" class="btn btn-danger">Open incident</a>
        @endcan
    </div>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="row row-deck row-cards">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Current status</div>
                    <div class="mt-1"><x-status-badge :status="$service->current_status" /></div>
                    <div class="mt-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="subheader">30-day uptime</div>
                                <div class="h2 mb-0">{{ number_format($uptime['uptime'], 2) }}%</div>
                                <div class="text-secondary small">{{ $uptime['successful'] }}/{{ $uptime['total'] }} checks</div>
                            </div>
                            <div class="col-6">
                                <div class="subheader">Avg response (24h)</div>
                                <div class="h2 mb-0">{{ $avgResponse !== null ? $avgResponse.' ms' : '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Configuration</h3>
                    <dl class="row">
                        <dt class="col-4">URL</dt>
                        <dd class="col-8 text-break">{{ $service->method->value }} {{ $service->url }}</dd>
                        <dt class="col-4">Group</dt>
                        <dd class="col-8">{{ $service->group?->name ?? '—' }}</dd>
                        <dt class="col-4">Interval / timeout</dt>
                        <dd class="col-8">every {{ $service->check_interval }}s &middot; timeout {{ $service->timeout }}s (connect {{ $service->connect_timeout }}s)</dd>
                        <dt class="col-4">Min failures before down</dt>
                        <dd class="col-8">{{ $service->min_failed_checks_down ?? 'global default ('.setting('min_failed_checks_down', 1).')' }}</dd>
                        <dt class="col-4">Incident thresholds</dt>
                        <dd class="col-8">{{ $service->failure_threshold }} failure(s) to open / {{ $service->recovery_threshold }} recoverie(s) to resolve</dd>
                        <dt class="col-4">Notifications</dt>
                        <dd class="col-8">failure: {{ $service->notify_on_failure ? 'on' : 'off' }} &middot; recovery: {{ $service->notify_on_recovery ? 'on' : 'off' }}</dd>
                        <dt class="col-4">Expected statuses</dt>
                        <dd class="col-8">{{ implode(', ', $service->expected_status_codes ?? []) }}</dd>
                        <dt class="col-4">Last checked</dt>
                        <dd class="col-8">{{ $service->last_checked_at?->diffForHumans() ?? 'never' }}</dd>
                        <dt class="col-4">Last success / failure</dt>
                        <dd class="col-8">{{ $service->last_success_at?->diffForHumans() ?? '—' }} / {{ $service->last_failure_at?->diffForHumans() ?? '—' }}</dd>
                        <dt class="col-4">Next check</dt>
                        <dd class="col-8">{{ $service->next_check_at?->diffForHumans() ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Check history</h3>
                </div>
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
                            @forelse ($checks as $check)
                                <tr>
                                    <td class="text-nowrap">{{ $check->checked_at->format('M j, H:i:s') }}</td>
                                    <td class="text-nowrap"><x-status-badge :status="$check->status" /></td>
                                    <td>{{ $check->http_status ?? '—' }}</td>
                                    <td>{{ $check->response_time !== null ? $check->response_time.' ms' : '—' }}</td>
                                    <td>
                                        @if ($check->error_message)
                                            <div class="text-danger">{{ \Illuminate\Support\Str::limit($check->error_message, 120) }}</div>
                                        @endif
                                        @if (! empty($check->assertion_result['failures']))
                                            <details>
                                                <summary class="cursor-pointer text-secondary">{{ count($check->assertion_result['failures']) }} failed assertion(s)</summary>
                                                <ul class="mb-0 ps-3 small">
                                                    @foreach ($check->assertion_result['failures'] as $failure)
                                                        <li><strong>{{ $failure['assertion'] ?? 'assertion' }}:</strong> {{ $failure['message'] ?? '' }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @elseif ($check->success)
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty">
                                            <p class="empty-title">No checks yet</p>
                                            <p class="empty-subtitle text-secondary">Queue a check to see history here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($checks->hasPages())
                    <div class="card-footer d-flex justify-content-end">{{ $checks->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@can('status.monitoring.run')
    <div class="modal modal-blur fade" id="test-saved-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test request &mdash; {{ $service->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="test-saved-body"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('test-saved-btn')?.addEventListener('click', async (event) => {
                const body = document.getElementById('test-saved-body');

                // Tabler's bundled Bootstrap opens the modal via data-bs-toggle.
                body.textContent = 'Running test…';

                try {
                    const response = await fetch(event.currentTarget.dataset.url, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                    });
                    const data = await response.json();
                    const lines = [
                        ['Result', data.result ?? data.message ?? 'error'],
                        ['HTTP status', data.outcome?.http_status ?? '—'],
                        ['Response time', data.outcome?.response_time_ms != null ? data.outcome.response_time_ms + ' ms' : '—'],
                        ['Final URL', data.outcome?.final_url || '—'],
                        ['Error', data.error || data.outcome?.error_message || data.outcome?.error || '—'],
                    ];

                    (data.assertions?.failures ?? []).forEach((f) => lines.push(['Failed assertion', (f.assertion ?? '') + ': ' + (f.message ?? '')]));

                    const dl = document.createElement('dl');
                    dl.className = 'row mb-0';
                    lines.forEach(([key, value]) => {
                        const dt = document.createElement('dt');
                        const dd = document.createElement('dd');
                        dt.className = 'col-4';
                        dd.className = 'col-8';
                        dt.textContent = key;
                        dd.textContent = String(value);
                        dl.append(dt, dd);
                    });
                    body.replaceChildren(dl);
                } catch (error) {
                    body.textContent = 'Test failed to run.';
                }
            });
        </script>
    @endpush
@endcan
