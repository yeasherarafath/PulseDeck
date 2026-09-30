@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Header presets')

@section('page-actions')
    @can('status.services.create')
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#preset-create">New preset</button>
    @endcan
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Header</th>
                        <th>Category</th>
                        <th>Input</th>
                        <th>Sensitive</th>
                        <th>Order</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($presets as $preset)
                        <tr>
                            <td>{{ $preset->name }}</td>
                            <td class="font-monospace">{{ $preset->header_name }}</td>
                            <td class="text-secondary">{{ $preset->category->label() }}</td>
                            <td class="text-secondary">{{ $preset->input_type }}</td>
                            <td>{{ $preset->is_sensitive ? 'Yes' : 'No' }}</td>
                            <td>{{ $preset->sort_order }}</td>
                            <td>{{ $preset->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <div class="btn-list">
                                    @can('status.services.update')
                                        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#preset-{{ $preset->id }}">Edit</button>
                                    @endcan
                                    @can('status.services.delete')
                                        <form method="POST" action="{{ route('admin.status.header-presets.destroy', $preset) }}" class="d-inline" onsubmit="return confirm('Delete preset {{ addslashes($preset->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><p class="empty-title">No presets yet</p><p class="empty-subtitle text-secondary">Presets suggest header names and values on the service form.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('status.services.create')
        <div class="modal modal-blur fade" id="preset-create" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.status.header-presets.store') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">New preset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @include('admin.status.header-presets._fields', ['preset' => null])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create preset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @foreach ($presets as $preset)
        @can('status.services.update')
            <div class="modal modal-blur fade" id="preset-{{ $preset->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form method="POST" action="{{ route('admin.status.header-presets.update', $preset) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit {{ $preset->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @include('admin.status.header-presets._fields', ['preset' => $preset])
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endforeach
@endsection
