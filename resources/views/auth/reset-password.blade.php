@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-4">Reset password</h2>
            <form method="POST" action="{{ route('password.update') }}" autocomplete="off" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}" />
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus />
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password" />
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Confirm new password</label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary w-100">Reset password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
