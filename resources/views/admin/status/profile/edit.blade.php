@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Profile')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
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

    <div class="row row-deck row-cards">
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.status.profile.update') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Profile information</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required" for="profile-name">Name</label>
                            <input type="text" class="form-control" id="profile-name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="profile-email">Email</label>
                            <input type="email" class="form-control" id="profile-email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email" />
                        </div>
                        <div class="form-hint">Role: {{ $user->roles->pluck('name')->join(', ') ?: 'none' }}</div>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-primary">Save profile</button></div>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <form method="POST" action="{{ route('admin.status.profile.password') }}">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Change password</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required" for="current-password">Current password</label>
                            <input type="password" class="form-control" id="current-password" name="current_password" required autocomplete="current-password" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="new-password">New password</label>
                            <input type="password" class="form-control" id="new-password" name="password" required autocomplete="new-password" />
                            <div class="form-hint">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="confirm-password">Confirm new password</label>
                            <input type="password" class="form-control" id="confirm-password" name="password_confirmation" required autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-primary">Change password</button></div>
                </div>
            </form>
        </div>
    </div>
@endsection
