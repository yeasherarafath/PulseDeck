@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Services')

@section('page-actions')
    @can('status.services.create')
        <a href="{{ route('admin.status.services.create') }}" class="btn btn-primary">New service</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.status.services.index') }}">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name…" />
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="group">
                            <option value="">All groups</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected(($filters['group'] ?? '') == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All statuses</option>
                            @foreach (\App\Enums\Status\ServiceStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="active">
                            <option value="">Active + paused</option>
                            <option value="1" @selected(($filters['active'] ?? '') === '1')>Active</option>
                            <option value="0" @selected(($filters['active'] ?? '') === '0')>Paused</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ route('admin.status.services.index') }}" class="btn">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Group</th>
                        <th class="w-1">Status</th>
                        <th>Response</th>
                        <th>Last checked</th>
                        <th>Interval</th>
                        <th>Active</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        @php($latest = $service->checks()->orderByDesc('checked_at')->orderByDesc('id')->first())
                        <tr>
                            <td>
                                <a href="{{ route('admin.status.services.show', $service) }}">{{ $service->name }}</a>
                                <div class="text-secondary small">{{ $service->method->value }} {{ \Illuminate\Support\Str::limit($service->url, 60) }}</div>
                            </td>
                            <td>{{ $service->group?->name ?? '—' }}</td>
                            <td class="text-nowrap"><x-status-badge :status="$service->current_status" /></td>
                            <td>{{ $latest?->response_time !== null ? $latest->response_time.' ms' : '—' }}</td>
                            <td class="text-nowrap">{{ $service->last_checked_at?->diffForHumans() ?? 'never' }}</td>
                            <td>{{ $service->check_interval >= 3600 ? ($service->check_interval / 3600).'h' : ($service->check_interval / 60).'m' }}</td>
                            <td>
                                @if ($service->is_active)
                                    <span class="badge bg-green-lt">Active</span>
                                @else
                                    <span class="badge bg-secondary-lt">Paused</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-list">
                                    <a href="{{ route('admin.status.services.show', $service) }}" class="btn btn-sm">View</a>
                                    @can('status.services.update')
                                        <a href="{{ route('admin.status.services.edit', $service) }}" class="btn btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.status.services.check', $service) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm" title="Queue an immediate check">Check now</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.status.services.pause', $service) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm" title="{{ $service->is_active ? 'Pause monitoring' : 'Resume monitoring' }}">
                                                {{ $service->is_active ? 'Pause' : 'Resume' }}
                                            </button>
                                        </form>
                                    @endcan
                                    @can('status.services.delete')
                                        <form method="POST" action="{{ route('admin.status.services.destroy', $service) }}" class="d-inline" onsubmit="return confirm('Delete {{ addslashes($service->name) }} and all its history?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    @if (array_filter($filters))
                                        <p class="empty-title">No services match these filters</p>
                                        <p class="empty-subtitle text-secondary">Try a different search or <a href="{{ route('admin.status.services.index') }}">reset the filters</a>.</p>
                                    @else
                                        <p class="empty-title">No services yet</p>
                                        <p class="empty-subtitle text-secondary">Create your first monitored service to get started.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($services->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $services->links() }}</div>
        @endif
    </div>
@endsection
