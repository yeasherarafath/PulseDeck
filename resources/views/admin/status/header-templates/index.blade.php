@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Header templates')

@section('page-actions')
    @can('status.services.create')
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#template-create">New template</button>
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
                        <th>Headers</th>
                        <th>Active</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>
                                {{ $template->name }}
                                @if ($template->description)<div class="text-secondary">{{ $template->description }}</div>@endif
                            </td>
                            <td class="font-monospace text-secondary">{{ implode(', ', array_keys($template->headers ?? [])) }}</td>
                            <td>{{ $template->is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                <div class="btn-list">
                                    @can('status.services.update')
                                        <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#template-{{ $template->id }}">Edit</button>
                                    @endcan
                                    @can('status.services.delete')
                                        <form method="POST" action="{{ route('admin.status.header-templates.destroy', $template) }}" class="d-inline" onsubmit="return confirm('Delete template {{ addslashes($template->name) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><p class="empty-title">No templates yet</p><p class="empty-subtitle text-secondary">Templates apply a header bundle to the service form in one click.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('status.services.create')
        <div class="modal modal-blur fade" id="template-create" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="POST" action="{{ route('admin.status.header-templates.store') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">New template</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @include('admin.status.header-templates._fields', ['template' => null])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create template</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @foreach ($templates as $template)
        @can('status.services.update')
            <div class="modal modal-blur fade" id="template-{{ $template->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <form method="POST" action="{{ route('admin.status.header-templates.update', $template) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit {{ $template->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @include('admin.status.header-templates._fields', ['template' => $template])
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
