@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Admins')

@section('page-actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#user-create">New admin</button>
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
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $listedUser)
                        <tr>
                            <td>
                                <span class="avatar avatar-sm me-2">{{ strtoupper(substr($listedUser->name ?? 'U', 0, 1)) }}</span>{{ $listedUser->name }}
                                @if ($listedUser->is(auth()->user()))
                                    <span class="badge bg-blue-lt ms-1">you</span>
                                @endif
                            </td>
                            <td class="text-secondary">{{ $listedUser->email }}</td>
                            <td>
                                @foreach ($listedUser->roles as $userRole)
                                    <span class="badge bg-secondary-lt">{{ $userRole->name }}</span>
                                @endforeach
                            </td>
                            <td class="text-secondary">{{ $listedUser->created_at?->format('M j, Y') }}</td>
                            <td class="text-end">
                                <div class="btn-list">
                                    <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#user-{{ $listedUser->id }}">Edit</button>
                                    @if (! $listedUser->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.status.users.destroy', $listedUser) }}" class="d-inline" onsubmit="return confirm('Delete admin {{ addslashes($listedUser->email) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><p class="empty-title">No admins yet</p><p class="empty-subtitle text-secondary">Create the first admin account.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">{{ $users->links() }}</div>
    </div>

    <div class="modal modal-blur fade" id="user-create" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('admin.status.users.store') }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New admin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" required autocomplete="off" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Email</label>
                            <input type="email" class="form-control" name="email" value="{{ old('email') }}" required autocomplete="off" />
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label required">Password</label>
                                <input type="password" class="form-control" name="password" required autocomplete="new-password" />
                                <div class="form-hint">Minimum 8 characters.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label required">Confirm password</label>
                                <input type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" />
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label required">Role</label>
                            <select class="form-select" name="role">
                                @foreach ($roles as $roleName)
                                    <option value="{{ $roleName }}" @selected(old('role', 'status-viewer') === $roleName)>{{ $roleName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create admin</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($users as $listedUser)
        <div class="modal modal-blur fade" id="user-{{ $listedUser->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.status.users.update', $listedUser) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $listedUser->email }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name', $listedUser->name) }}" required autocomplete="off" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Email</label>
                                <input type="email" class="form-control" name="email" value="{{ old('email', $listedUser->email) }}" required autocomplete="off" />
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">New password</label>
                                    <input type="password" class="form-control" name="password" autocomplete="new-password" placeholder="Leave blank to keep" />
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Confirm password</label>
                                    <input type="password" class="form-control" name="password_confirmation" autocomplete="new-password" />
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label required">Role</label>
                                <select class="form-select" name="role" @disabled($listedUser->is(auth()->user()))>
                                    @foreach ($roles as $roleName)
                                        <option value="{{ $roleName }}" @selected(old('role', $listedUser->roles->first()?->name) === $roleName)>{{ $roleName }}</option>
                                    @endforeach
                                </select>
                                @if ($listedUser->is(auth()->user()))
                                    <div class="form-hint">You cannot change your own role.</div>
                                @endif
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
    @endforeach
@endsection
