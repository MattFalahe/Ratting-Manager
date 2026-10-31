@extends('ratting-manager::layouts.page')

@section('title', trans('ratting-manager::ratter.lookup_title'))
@section('page_header', trans('ratting-manager::ratter.lookup_title'))

@push('head')
    <link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('rm-tabs')
    @include('ratting-manager::partials.page-tabs', ['icon' => 'fa-search', 'label' => trans('ratting-manager::menu.ratter_lookup')])
@endsection

@section('rm-body')
    @if($notConfigured)
        @include('ratting-manager::partials.not-configured', ['isAdmin' => \RattingManager\Support\Access::isAdmin()])
    @else
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search"></i> {{ trans('ratting-manager::ratter.lookup_title') }}</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">{{ trans('ratting-manager::ratter.lookup_intro') }}</p>
                <table class="table table-sm table-hover" id="rm-ratters">
                    <thead>
                        <tr>
                            <th>{{ trans('ratting-manager::dashboard.player') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::ratter.characters') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::ratter.income_90d') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::ratter.hours_90d') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::dashboard.isk_per_hour') }}</th>
                            <th>{{ trans('ratting-manager::dashboard.last_tick') }}</th>
                            <th>{{ trans('ratting-manager::dashboard.coverage') }}</th>
                            <th>{{ trans('ratting-manager::ratter.wallet') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('ratting-manager.ratters.show', $row['main_id']) }}">{{ $row['name'] }}</a>
                                    @include('ratting-manager::partials.player-tags', ['row' => $row])
                                </td>
                                <td class="text-right">{{ count($row['characters']) }}</td>
                                <td class="text-right" data-order="{{ $row['income'] }}">@rmisk($row['income'])</td>
                                <td class="text-right" data-order="{{ $row['minutes'] }}">{{ \RattingManager\Support\Format::hours($row['minutes']) }}</td>
                                <td class="text-right" data-order="{{ $row['isk_per_hour'] }}">@rmisk($row['isk_per_hour'])</td>
                                <td data-order="{{ $row['last_tick'] }}">{{ $row['last_tick'] ? substr($row['last_tick'], 0, 16) : '-' }}</td>
                                <td>@include('ratting-manager::partials.coverage-badge', ['coverage' => $row['coverage']])</td>
                                <td>{{ $row['wallet_token'] ? trans('ratting-manager::ratter.yes') : trans('ratting-manager::ratter.no') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@section('rm-after')
    @include('ratting-manager::partials.character-lookup')
@endsection


@push('javascript')
@include('ratting-manager::partials.datatables')
<script>
$(function () {
    if ($.fn.DataTable && document.getElementById('rm-ratters')) {
        $('#rm-ratters').DataTable({ order: [[5, 'desc']], pageLength: 50 });
    }
});
</script>
@endpush
