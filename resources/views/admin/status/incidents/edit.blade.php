@extends('layouts.admin-tabler')

@section('page-pretitle', 'Incidents')
@section('page-title', 'Edit: '.$incident->title)

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.status.incidents.update', $incident) }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="i-title">Title</label>
                        <input type="text" class="form-control" id="i-title" name="title" value="{{ old('title', $incident->title) }}" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="i-service">Service</label>
                        <select class="form-select" id="i-service" name="service_id">
                            <option value="">No specific service</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id', $incident->service_id) == $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                        @if ($incident->service)
                            @can('status.services.view')
                                <div class="form-hint"><a href="{{ route('admin.status.services.show', $incident->service) }}">View {{ $incident->service->name }}</a></div>
                            @endcan
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Impact</label>
                        <select class="form-select" name="impact">
                            @foreach (\App\Enums\Status\IncidentImpact::cases() as $impact)
                                <option value="{{ $impact->value }}" @selected(old('impact', $incident->impact->value) === $impact->value)>{{ $impact->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Status</label>
                        <select class="form-select" name="status">
                            @foreach (\App\Enums\Status\IncidentStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $incident->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('admin.status.incidents.show', $incident) }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
@endsection
