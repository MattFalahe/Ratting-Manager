@extends('ratting-manager::layouts.page')

@section('title', trans('ratting-manager::ratter.title', ['name' => $mainName]))
@section('page_header', trans('ratting-manager::ratter.title', ['name' => $mainName]))

@section('rm-tabs')
    @include('ratting-manager::partials.page-tabs', ['icon' => 'fa-user', 'label' => $mainName])
@endsection

@section('rm-body')
    <p><a href="{{ route('ratting-manager.ratters.index') }}"><i class="fas fa-arrow-left"></i> {{ trans('ratting-manager::ratter.back') }}</a></p>

    @if($standing['left'] || $standing['seat_auth'])
        <p class="text-muted">
            @include('ratting-manager::partials.player-tags', ['row' => $standing])
            @if($standing['left'])
                {{ trans('ratting-manager::common.left_title') }}
                @if($standing['left_last_tick'])
                    {{ trans('ratting-manager::common.left_last_tick', ['time' => substr($standing['left_last_tick'], 0, 16)]) }}
                @endif
                @if($corporationCut > 0)
                    {{ trans('ratting-manager::ratter.left_cut') }} @rmisk($corporationCut).
                @endif
            @elseif($standing['seat_auth'])
                {{ trans('ratting-manager::common.not_authed_' . $standing['seat_auth']) }}
            @endif
        </p>
    @endif

    @include('ratting-manager::dashboard.partials.personal', ['heading' => $mainName])

    <div class="row">
        <div class="col-12">
            <div class="card card-dark">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-database"></i> {{ trans('ratting-manager::ratter.sources') }}</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::ratter.sources_hint') }}</p>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ trans('ratting-manager::common.character') }}</th>
                                <th>{{ trans('ratting-manager::ratter.corporation_now') }}</th>
                                <th>{{ trans('ratting-manager::ratter.wallet') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::ratter.paired') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::ratter.character_only') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::ratter.corporation_only') }}</th>
                                <th>{{ trans('ratting-manager::dashboard.coverage') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sources as $source)
                                <tr>
                                    <td>
                                        {{ $source['name'] }}
                                        @include('ratting-manager::partials.player-tags', ['row' => ['seat_auth' => $source['seat_auth']]])
                                    </td>
                                    <td>{{ $source['corporation'] ?? '-' }}</td>
                                    <td>
                                        {{ $source['wallet_token'] ? trans('ratting-manager::ratter.yes') : trans('ratting-manager::ratter.no') }}
                                        @if($source['wallet_gap'])
                                            <br><small class="text-warning" title="{{ trans('ratting-manager::dashboard.wallet_gaps_hint') }}"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::ratter.wallet_gap', ['since' => substr($source['wallet_gap']['since'], 0, 10), 'by' => substr($source['wallet_gap']['fix_by'], 0, 10)]) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $source['counts']['paired'] }}</td>
                                    <td class="text-right">{{ $source['counts']['character'] }}</td>
                                    <td class="text-right">{{ $source['counts']['corporation'] }}</td>
                                    <td>@include('ratting-manager::partials.coverage-badge', ['coverage' => $source['coverage']])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('rm-after')
    @include('ratting-manager::partials.character-lookup')
@endsection


@push('javascript')
<script src="{{ \RattingManager\Support\Asset::url('js/vendor/chart.min.js') }}"></script>
<script src="{{ \RattingManager\Support\Asset::url('js/eve-time.js') }}"></script>
<script src="{{ \RattingManager\Support\Asset::url('js/ratting-charts.js') }}"></script>
@php
    $chartText = trans('ratting-manager::dashboard') + ['insights' => trans('ratting-manager::insights')];
@endphp
<script>
RattingCharts.personal(@json($personalCharts), @json($chartText));
</script>
@endpush
