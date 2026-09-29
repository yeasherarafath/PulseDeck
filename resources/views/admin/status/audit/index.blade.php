@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Audit log')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.status.audit-logs') }}">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="action" value="{{ $filter }}" placeholder="Filter by action…" />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('admin.status.audit-logs') }}" class="btn">Reset</a>
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
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Subject</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('M j, H:i:s') }}</td>
                            <td>{{ $log->user?->email ?? 'system' }}</td>
                            <td><code>{{ $log->action }}</code></td>
                            <td class="text-secondary small">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                            <td class="text-secondary small">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><p class="empty-title">No audit entries</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $logs->links() }}</div>
        @endif
    </div>
@endsection
