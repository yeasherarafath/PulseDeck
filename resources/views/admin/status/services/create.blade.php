@extends('layouts.admin-tabler')

@section('page-pretitle', 'Services')
@section('page-title', 'New service')

@section('content')
    @include('admin.status.services._form', [
        'action' => route('admin.status.services.store'),
        'formMethod' => 'POST',
        'submitLabel' => 'Create service',
        'canTest' => auth()->user()->can('status.monitoring.run'),
        'testUrl' => route('admin.status.services.test-unsaved'),
    ])
@endsection
