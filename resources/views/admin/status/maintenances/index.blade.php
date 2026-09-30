@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Maintenance')

@section('page-actions')
    @can('status.maintenance.create')
        <a href="{{ route('admin.status.maintenances.create') }}" class="btn btn-primary">Schedule maintenance</a>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Services</th>
                        <th>Window</th>
                        <th class="w-1">Status</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($maintenances as $maintenance)
                        <tr>
                            <td>{{ $maintenance->title }}</td>
                            <td>
                                @forelse ($maintenance->services as $service)
                                    @can('status.services.view')
                                        <a href="{{ route('admin.status.services.show', $service) }}">{{ $service->name }}</a>{{ $loop->last ? '' : ', ' }}
                                    @else
                                        {{ $service->name }}{{ $loop->last ? '' : ', ' }}
                                    @endcan
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td class="text-nowrap">{{ setting_time($maintenance->starts_at) }} – {{ setting_time($maintenance->ends_at) }}</td>
                            <td class="text-nowrap"><x-status-badge :status="$maintenance->status" /></td>
                            <td class="text-end">
                                <div class="btn-list">
                                    @can('status.maintenance.update')
                                        <a href="{{ route('admin.status.maintenances.edit', $maintenance) }}" class="btn btn-sm">Edit</a>
                                        @if (in_array($maintenance->status->value, ['scheduled', 'active'], true))
                                            <form method="POST" action="{{ route('admin.status.maintenances.cancel', $maintenance) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm">Cancel</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('status.maintenance.delete')
                                        <form method="POST" action="{{ route('admin.status.maintenances.destroy', $maintenance) }}" class="d-inline" onsubmit="return confirm('Delete this maintenance window?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><p class="empty-title">No maintenance scheduled</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
