@extends('layouts.admin-tabler')

@section('page-pretitle', 'Status admin')
@section('page-title', 'Dashboard')

@section('content')
    <div class="row row-deck row-cards">
        @foreach (['Total Services', 'Operational', 'Degraded', 'Outage', 'Maintenance'] as $card)
            <div class="col-sm-6 col-lg-4 col-xl">
                <div class="card">
                    <div class="card-body">
                        <div class="subheader">{{ $card }}</div>
                        <div class="h1 mb-0">&mdash;</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Monitoring engine scaffold</h3>
                    <p class="text-secondary mb-0">
                        Phase 0 is in place: layouts, theme toggle, authentication, and permissions.
                        Live service data, incidents, and charts arrive in later phases (see
                        <code>final-plan.md</code>).
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
