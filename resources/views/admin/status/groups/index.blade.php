@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Service groups')

@section('page-actions')
    @can('status.services.create')
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#group-create">New group</button>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Services</th>
                        <th>Order</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td>{{ $group->name }}</td>
                            <td class="text-secondary">{{ $group->slug }}</td>
                            <td>{{ $group->services_count }}</td>
                            <td>{{ $group->sort_order }}</td>
                            <td>{{ $group->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    @can('status.services.update')
                                        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#group-{{ $group->id }}">Edit</button>
                                    @endcan
                                    @can('status.services.delete')
                                        <form method="POST" action="{{ route('admin.status.groups.destroy', $group) }}" class="d-inline" onsubmit="return confirm('Delete group {{ addslashes($group->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><p class="empty-title">No groups yet</p><p class="empty-subtitle text-secondary">Groups organize services on the public page.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('status.services.create')
        <div class="modal modal-blur fade" id="group-create" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.status.groups.store') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">New group</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name" required placeholder="Websites" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Slug (auto-generated)</label>
                                <input type="text" class="form-control" name="slug" placeholder="websites" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">Sort order</label>
                                    <input type="number" class="form-control" name="sort_order" value="0" min="0" />
                                </div>
                                <div class="col-6 d-flex align-items-end">
                                    <label class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="is_active" value="1" checked />
                                        <span class="form-check-label">Active</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create group</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @foreach ($groups as $group)
        @can('status.services.update')
            <div class="modal modal-blur fade" id="group-{{ $group->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" action="{{ route('admin.status.groups.update', $group) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit {{ $group->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" value="{{ $group->name }}" required />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Slug</label>
                                    <input type="text" class="form-control" name="slug" value="{{ $group->slug }}" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="2">{{ $group->description }}</textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">Sort order</label>
                                        <input type="number" class="form-control" name="sort_order" value="{{ $group->sort_order }}" min="0" />
                                    </div>
                                    <div class="col-6 d-flex align-items-end">
                                        <label class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($group->is_active) />
                                            <span class="form-check-label">Active</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endforeach
@endsection
