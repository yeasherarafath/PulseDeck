@extends('layouts.guest')

@section('title', 'Page not found')

@section('content')
    <div class="empty">
        <div class="empty-header">404</div>
        <p class="empty-title">Oops… You just found an error page</p>
        <p class="empty-subtitle text-secondary">The page you requested could not be found.</p>
        <div class="empty-action">
            <a href="{{ route('status.index') }}" class="btn btn-primary">Take me to the status page</a>
        </div>
    </div>
@endsection
