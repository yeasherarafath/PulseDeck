@extends('layouts.admin-tabler')

@section('page-pretitle', 'Maintenance')
@section('page-title', 'Schedule maintenance')

@section('content')
    @include('admin.status.maintenances.form')
@endsection
