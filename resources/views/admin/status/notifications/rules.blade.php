@extends('layouts.admin-tabler')

@section('page-pretitle', 'Notifications')
@section('page-title', 'Rules')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.channels') }}">Channels</a></li>
                <li class="nav-item"><span class="nav-link active">Rules</span></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.subscribers') }}">Subscribers</a></li>
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>Event</th>
                        <th>Service</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td>{{ $rule->channel->name }}</td>
                            <td>{{ $rule->event->label() }}</td>
                            <td>{{ $rule->service?->name ?? 'All services' }}</td>
                            <td>{{ $rule->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.status.notifications.rules.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><p class="empty-title">No rules yet</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.status.notifications.rules.store') }}">
        @csrf
        <div class="card">
            <div class="card-header"><h3 class="card-title">New rule</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Channel</label>
                        <select class="form-select" name="channel_id" required>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->name }} ({{ $channel->type->label() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Event</label>
                        <select class="form-select" name="event" required>
                            @foreach ($events as $event)
                                <option value="{{ $event->value }}">{{ $event->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Service (empty = all)</label>
                        <select class="form-select" name="service_id">
                            <option value="">All services</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" checked />
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">Create rule</button></div>
        </div>
    </form>
@endsection
