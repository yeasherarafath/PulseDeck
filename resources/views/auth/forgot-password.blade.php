@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-4">Forgot password</h2>
            <p class="text-secondary mb-4">Enter your email address and your password will be reset and emailed to you.</p>
            @if (session('status'))
                <div class="alert alert-success" role="alert">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('password.email') }}" autocomplete="off" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required autofocus />
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary w-100">Send reset link</button>
                </div>
            </form>
            <div class="text-center text-secondary mt-3">
                <a href="{{ route('login') }}">Back to login</a>
            </div>
        </div>
    </div>
@endsection
