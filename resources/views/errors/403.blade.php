@extends('layouts.guest')

@section('title', 'Forbidden')

@section('content')
    <div class="empty">
        <div class="empty-header">403</div>
        <p class="empty-title">You are not authorized here</p>
        <p class="empty-subtitle text-secondary">Please sign in with an account that has access, or contact an administrator.</p>
        <div class="empty-action">
            <a href="{{ route('status.index') }}" class="btn btn-primary">Take me to the status page</a>
        </div>
    </div>
@endsection
