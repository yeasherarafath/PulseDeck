@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Incidents')

@section('page-actions')
    @can('status.incidents.create')
        <a href="{{ route('admin.status.incidents.create') }}" class="btn btn-primary">New incident</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.status.incidents.index') }}">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach (\App\Enums\Status\IncidentStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($filter === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
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
                        <th>Incident</th>
                        <th>Service</th>
                        <th class="w-1">Status</th>
                        <th class="w-1">Impact</th>
                        <th>Started</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr>
                            <td><a href="{{ route('admin.status.incidents.show', $incident) }}">{{ $incident->title }}</a></td>
                            <td>{{ $incident->service?->name ?? '—' }}</td>
                            <td class="text-nowrap"><x-status-badge :status="$incident->status" /></td>
                            <td class="text-nowrap"><x-status-badge :status="$incident->impact" /></td>
                            <td class="text-nowrap">{{ $incident->started_at->diffForHumans() }}</td>
                            <td class="text-end">
                                @can('status.incidents.update')
                                    <a href="{{ route('admin.status.incidents.edit', $incident) }}" class="btn btn-sm">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><p class="empty-title">No incidents</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incidents->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $incidents->links() }}</div>
        @endif
    </div>
@endsection
