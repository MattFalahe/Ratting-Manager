{{--
    The shell a standalone page sits in, as Mining Manager's pages do: a tab
    naming the page on top and the same dark body the dashboard's tabs have,
    holding the page's cards. A page fills rm-tabs with partials.page-tabs,
    rm-body with its content, and rm-after with anything outside the shell.
--}}
@extends('web::layouts.grids.12')

@push('head')
    @include('ratting-manager::partials.head')
@endpush

@section('full')
<div class="ratting-manager-dashboard">
    <div class="card card-dark card-tabs rm-page">
        <div class="card-header p-0 pt-1">
            <ul class="nav nav-tabs">
                @yield('rm-tabs')
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                @yield('rm-body')
            </div>
        </div>
    </div>
</div>
@yield('rm-after')
@endsection
