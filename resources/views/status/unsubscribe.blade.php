@extends('layouts.public-status')

@section('meta-title', 'Unsubscribe | '.setting('app_name', config('app.name', 'Status')))
@section('meta-description', 'Unsubscribe from status emails.')

@section('content')
    <div class="card card-lg mx-auto" style="max-width: 32rem;">
        <div class="card-body">
            <h1 class="card-title h2">Unsubscribe from status emails?</h1>
            <p class="text-secondary">You will stop receiving incident emails from {{ setting('app_name', config('app.name')) }}.</p>
            <form method="POST" action="{{ route('status.unsubscribe.confirm', $token) }}" class="d-flex gap-2">
                @csrf
                <button type="submit" class="btn btn-danger">Unsubscribe</button>
                <a href="{{ route('status.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
