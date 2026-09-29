@extends('layouts.public-status')

@section('meta-title', $incident->title)
@section('meta-description', 'Incident timeline: '.$incident->title.'.')

@section('content')
    <div class="card card-lg mb-3">
        <div class="card-body">
            <div class="page-pretitle">{{ $incident->service?->name ?? 'Incident' }}</div>
            <h1 class="card-title h2">{{ $incident->title }}</h1>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <x-status-badge :status="$incident->status" />
                <x-status-badge :status="$incident->impact" />
            </div>
            <div class="mt-2 text-secondary">
                Started {{ setting_time($incident->started_at, 'M j, Y H:i') }}
                @if ($incident->resolved_at)
                    &middot; Resolved {{ setting_time($incident->resolved_at, 'M j, Y H:i') }}
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Timeline</h3>
        </div>
        <div class="card-body">
            <ul class="steps steps-vertical">
                @forelse ($incident->updates as $update)
                    <li class="step-item">
                        <div class="h4 m-0">{{ $update->status->label() }}</div>
                        <div class="text-secondary">
                            {{ setting_time($update->created_at, 'M j, Y H:i') }} — {{ $update->message }}
                        </div>
                    </li>
                @empty
                    <li class="step-item">
                        <div class="text-secondary">No updates posted yet.</div>
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
