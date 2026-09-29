@extends('layouts.guest')

@section('title', 'Server error')

@section('content')
    <div class="empty">
        <div class="empty-header">500</div>
        <p class="empty-title">Something went wrong on our side</p>
        <p class="empty-subtitle text-secondary">The team has been notified. Please try again in a few minutes.</p>
        <div class="empty-action">
            <a href="{{ route('status.index') }}" class="btn btn-primary">Take me to the status page</a>
        </div>
    </div>
@endsection
