@extends('layouts.admin-tabler')

@section('page-pretitle', 'Notifications')
@section('page-title', 'Subscribers')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.channels') }}">Channels</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.rules') }}">Rules</a></li>
                <li class="nav-item"><span class="nav-link active">Subscribers</span></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.status.notifications.deliveries') }}">Deliveries</a></li>
            </ul>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Verified</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscribers as $subscriber)
                        <tr>
                            <td>{{ $subscriber->email }}</td>
                            <td>{{ $subscriber->verified_at ? 'Yes' : 'No' }}</td>
                            <td>{{ $subscriber->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <form method="POST" action="{{ route('admin.status.notifications.subscribers.toggle', $subscriber) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">{{ $subscriber->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.status.notifications.subscribers.destroy', $subscriber) }}" class="d-inline" onsubmit="return confirm('Remove this subscriber?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><p class="empty-title">No subscribers yet</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscribers->hasPages())
            <div class="card-footer d-flex justify-content-end">{{ $subscribers->links() }}</div>
        @endif
    </div>
@endsection
