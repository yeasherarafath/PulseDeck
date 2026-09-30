@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Roles')

@section('page-actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#role-create">New role</button>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Admins</th>
                        <th>Permissions</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $listedRole)
                        <tr>
                            <td>
                                {{ $listedRole->name }}
                                @if (in_array($listedRole->name, \App\Http\Controllers\Admin\Status\RoleController::BUILTIN_ROLES, true))
                                    <span class="badge bg-blue-lt ms-1">built-in</span>
                                @endif
                            </td>
                            <td>{{ $listedRole->users_count }}</td>
                            <td class="text-secondary">{{ $listedRole->permissions->count() }} of {{ collect($groupedPermissions)->flatten()->count() }}</td>
                            <td class="text-end">
                                <div class="btn-list">
                                    <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#role-{{ $listedRole->id }}">Edit</button>
                                    @if (! in_array($listedRole->name, \App\Http\Controllers\Admin\Status\RoleController::BUILTIN_ROLES, true))
                                        <form method="POST" action="{{ route('admin.status.roles.destroy', $listedRole) }}" class="d-inline" onsubmit="return confirm('Delete role {{ addslashes($listedRole->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><p class="empty-title">No roles yet</p><p class="empty-subtitle text-secondary">Create a role to group permissions.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal modal-blur fade" id="role-create" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('admin.status.roles.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" required placeholder="partner-support" />
                            <div class="form-hint">Letters, numbers, dashes and underscores.</div>
                        </div>
                        @foreach ($groupedPermissions as $area => $permissions)
                            <div class="mb-3">
                                <div class="form-label">{{ ucfirst($area) }}</div>
                                @foreach ($permissions as $permission)
                                    <label class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true)) />
                                        <span class="form-check-label">{{ $permission }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($roles as $listedRole)
        <div class="modal modal-blur fade" id="role-{{ $listedRole->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="POST" action="{{ route('admin.status.roles.update', $listedRole) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $listedRole->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $listedRole->name }}" required @readonly(in_array($listedRole->name, \App\Http\Controllers\Admin\Status\RoleController::BUILTIN_ROLES, true)) />
                                @if (in_array($listedRole->name, \App\Http\Controllers\Admin\Status\RoleController::BUILTIN_ROLES, true))
                                    <div class="form-hint">Built-in roles cannot be renamed.</div>
                                @endif
                            </div>
                            @if ($listedRole->name === 'super-admin')
                                <div class="alert alert-info" role="alert">Super-admin always keeps every permission.</div>
                            @else
                                @php($current = $listedRole->permissions->pluck('name')->all())
                                @foreach ($groupedPermissions as $area => $permissions)
                                    <div class="mb-3">
                                        <div class="form-label">{{ ucfirst($area) }}</div>
                                        @foreach ($permissions as $permission)
                                            <label class="form-check form-check-inline">
                                                <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $current, true)) />
                                                <span class="form-check-label">{{ $permission }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
