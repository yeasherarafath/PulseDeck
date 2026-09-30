@extends('layouts.admin-tabler')

@section('page-pretitle', 'Incidents')
@section('page-title', $incident->title)

@section('page-actions')
    @can('status.incidents.update')
        <a href="{{ route('admin.status.incidents.edit', $incident) }}" class="btn btn-primary">Edit</a>
    @endcan
    @can('status.incidents.delete')
        <form method="POST" action="{{ route('admin.status.incidents.destroy', $incident) }}" class="d-inline" onsubmit="return confirm('Delete this incident and its timeline?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="row row-deck row-cards">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-2">
                        <x-status-badge :status="$incident->status" />
                        <x-status-badge :status="$incident->impact" />
                    </div>
                    <dl class="row">
                        <dt class="col-5">Service</dt>
                        <dd class="col-7">{{ $incident->service?->name ?? '—' }}</dd>
                        <dt class="col-5">Started</dt>
                        <dd class="col-7">{{ $incident->started_at->format('M j, Y H:i') }}</dd>
                        <dt class="col-5">Resolved</dt>
                        <dd class="col-7">{{ $incident->resolved_at?->format('M j, Y H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Timeline</h3>
                </div>
                <div class="card-body">
                    <ul class="steps steps-vertical">
                        @foreach ($incident->updates as $update)
                            <li class="step-item">
                                <div class="h4 m-0">{{ $update->status->label() }}</div>
                                <div class="text-secondary">{{ $update->created_at->format('M j, Y H:i') }} — {{ $update->message }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @can('status.incidents.update')
                <div class="card mt-3">
                    <form method="POST" action="{{ route('admin.status.incidents.updates.store', $incident) }}">
                        @csrf
                        <div class="card-body">
                            <h3 class="card-title">Post an update</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="upd-status">Status</label>
                                    <select class="form-select" id="upd-status" name="status">
                                        @foreach (\App\Enums\Status\IncidentStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected($incident->status->value === $status->value)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label required" for="upd-message">Message</label>
                                    <textarea class="form-control @error('message') is-invalid @enderror" id="upd-message" name="message" rows="3" required>{{ old('message') }}</textarea>
                                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Post update</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endsection
