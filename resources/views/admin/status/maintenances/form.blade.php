@extends('layouts.admin-tabler')

@section('page-pretitle', 'Maintenance')
@section('page-title', isset($maintenance) ? 'Edit: '.$maintenance->title : 'Schedule maintenance')

@section('content')
    <div class="card">
        <form method="POST" action="{{ isset($maintenance) ? route('admin.status.maintenances.update', $maintenance) : route('admin.status.maintenances.store') }}">
            @csrf
            @if (isset($maintenance))
                @method('PUT')
            @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="m-title">Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="m-title" name="title" value="{{ old('title', $maintenance->title ?? '') }}" required />
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="m-desc">Description</label>
                        <textarea class="form-control" id="m-desc" name="description" rows="3">{{ old('description', $maintenance->description ?? '') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="m-start">Starts at</label>
                        <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="m-start" name="starts_at" value="{{ old('starts_at', isset($maintenance) ? $maintenance->starts_at->format('Y-m-d\TH:i') : '') }}" required />
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="m-end">Ends at</label>
                        <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="m-end" name="ends_at" value="{{ old('ends_at', isset($maintenance) ? $maintenance->ends_at->format('Y-m-d\TH:i') : '') }}" required />
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label required">Affected services</label>
                        @error('services')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @php($selected = old('services', isset($maintenance) ? $maintenance->services->pluck('id')->all() : []))
                        @foreach ($services as $service)
                            <label class="form-check">
                                <input type="checkbox" class="form-check-input" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, (array) $selected)) />
                                <span class="form-check-label">{{ $service->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ isset($maintenance) ? 'Save changes' : 'Schedule' }}</button>
                <a href="{{ route('admin.status.maintenances.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
@endsection
