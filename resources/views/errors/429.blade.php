@extends('layouts.guest')

@section('title', 'Too many requests')

@section('content')
    <div class="empty">
        <div class="empty-header">429</div>
        <p class="empty-title">Slow down a little</p>
        <p class="empty-subtitle text-secondary">You made too many requests in a short time. Please wait a moment and try again.</p>
        <div class="empty-action">
            <a href="{{ route('status.index') }}" class="btn btn-primary">Take me to the status page</a>
        </div>
    </div>
@endsection
