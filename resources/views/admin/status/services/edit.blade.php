@extends('layouts.admin-tabler')

@section('page-pretitle', 'Services')
@section('page-title', 'Edit: '.$service->name)

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @include('admin.status.services._form', [
        'action' => route('admin.status.services.update', $service),
        'formMethod' => 'PUT',
        'submitLabel' => 'Save changes',
        'canTest' => auth()->user()->can('status.monitoring.run'),
        'testUrl' => route('admin.status.services.test', $service),
    ])
@endsection
