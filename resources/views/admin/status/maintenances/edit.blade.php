@extends('layouts.admin-tabler')

@section('page-pretitle', 'Maintenance')
@section('page-title', 'Edit: '.$maintenance->title)

@section('content')
    @include('admin.status.maintenances.form')
@endsection
