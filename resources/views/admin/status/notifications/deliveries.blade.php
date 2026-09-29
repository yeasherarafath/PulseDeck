@extends('layouts.admin-tabler')

@section('page-pretitle', 'Notifications')
@section('page-title', 'Deliveries')

@section('content')
    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.channels') }}">Channels</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.rules') }}">Rules</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.subscribers') }}">Subscribers</a></li>
                <li class="nav-item"><span class="nav-link active">Deliveries</span></li>
            </ul>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.status.notifications.deliveries') }}">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-select" name="event" onchange="this.form.submit()">
                            <option value="">All events</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->value }}" @selected(($filters['event'] ?? '') === $event->value)>{{ $event->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All outcomes</option>
                            @foreach (['sent', 'failed', 'skipped'] as $outcome)
                                <option value="{{ $outcome }}" @selected(($filters['status'] ?? '') === $outcome)>{{ ucfirst($outcome) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Channel</th>
                        <th>Event</th>
                        <th>Service</th>
                        <th>Recipients</th>
                        <th>Outcome</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="text-nowrap">{{ $delivery->created_at->format('M j, H:i:s') }}</td>
                            <td>{{ $delivery->channel?->name ?? '—' }}</td>
                            <td>{{ \App\Enums\Status\NotificationEvent::tryFrom($delivery->event)?->label() ?? $delivery->event }}</td>
                            <td>{{ $delivery->service?->name ?? '—' }}</td>
                            <td>{{ $delivery->recipient_count }}</td>
                            <td>
                                @if ($delivery->status === 'sent')
                                    <span class="badge bg-green-lt">Sent</span>
                                @elseif ($delivery->status === 'failed')
                                    <span class="badge bg-red-lt">Failed</span>
                                @else
                                    <span class="badge bg-secondary-lt">Skipped</span>
                                @endif
                            </td>
                            <td class="text-secondary small">{{ \Illuminate\Support\Str::limit($delivery->error ?? '', 100) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><p class="empty-title">No deliveries yet</p><p class="empty-subtitle text-secondary">Deliveries appear here as notifications fire.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($deliveries->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $deliveries->links() }}</div>
        @endif
    </div>
@endsection
