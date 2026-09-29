@extends('layouts.admin-tabler')

@section('page-pretitle', 'Incidents')
@section('page-title', 'New incident')

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.status.incidents.store') }}">
            @csrf
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="i-title">Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="i-title" name="title" value="{{ old('title') }}" required />
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="i-service">Service</label>
                        <select class="form-select" id="i-service" name="service_id">
                            <option value="">No specific service</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Impact</label>
                        <select class="form-select" name="impact">
                            @foreach (\App\Enums\Status\IncidentImpact::cases() as $impact)
                                <option value="{{ $impact->value }}" @selected(old('impact') === $impact->value)>{{ $impact->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="i-message">Initial update</label>
                        <textarea class="form-control @error('message') is-invalid @enderror" id="i-message" name="message" rows="3" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create incident</button>
                <a href="{{ route('admin.status.incidents.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
@endsection
