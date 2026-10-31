@extends('ratting-manager::layouts.page')

@section('title', trans('ratting-manager::report.title'))
@section('page_header', trans('ratting-manager::report.title'))

@push('head')
    <link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@section('rm-tabs')
    @include('ratting-manager::partials.page-tabs', ['icon' => 'fa-file-invoice-dollar', 'label' => trans('ratting-manager::menu.monthly_report')])
@endsection

@section('rm-body')
    @if($notConfigured)
        @include('ratting-manager::partials.not-configured', ['isAdmin' => \RattingManager\Support\Access::isAdmin()])
    @else
        @php $fmt = \RattingManager\Support\Format::class; @endphp
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> {{ $month->format('F Y') }}</h3>
                <div class="card-tools">
                    <form method="GET" action="{{ url()->current() }}" class="rm-filters">
                        @if(count($corporations) > 1)
                            <select name="corporation" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::common.corporation') }}">
                                <option value="">{{ trans('ratting-manager::common.all_corporations') }}</option>
                                @foreach($corporations as $id => $name)
                                    <option value="{{ $id }}" @if($pickedCorporation === $id && request('corporation')) selected @endif>{{ $name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <select name="month" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::insights.month') }}">
                            @foreach($monthChoices as $key => $label)
                                <option value="{{ $key }}" @if($pickedMonth === $key) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-rm-primary">{{ trans('ratting-manager::common.apply') }}</button></noscript>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">{{ trans('ratting-manager::report.intro') }}</p>
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="info-box bg-gradient-primary">
                            <span class="info-box-icon"><i class="fas fa-crosshairs"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.isk_ratted') }}</span>
                                <span class="info-box-number">@rmisk($totals['ratted'])</span>
                                <small>
                                    @if($change !== null)
                                        {{ trans('ratting-manager::report.change', ['change' => ($change > 0 ? '+' : '') . $change]) }}
                                    @else
                                        {{ trans('ratting-manager::dashboard.isk_ratted_hint') }}
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="info-box bg-gradient-danger">
                            <span class="info-box-icon"><i class="fas fa-landmark"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.corp_tax') }}</span>
                                <span class="info-box-number">@rmisk($totals['corp_tax'])</span>
                                <small>{{ $taxShare === null ? trans('ratting-manager::dashboard.corp_tax_hint') : trans('ratting-manager::report.tax_share', ['share' => number_format($taxShare, 1)]) }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="info-box bg-gradient-success">
                            <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::report.received') }}</span>
                                <span class="info-box-number">@rmisk($totals['income'])</span>
                                <small>{{ trans('ratting-manager::report.received_split', ['bounties' => $fmt::isk($totals['bounty']), 'ess' => $fmt::isk($totals['ess'])]) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ $totals['players'] }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.active_players') }} <small>({{ trans('ratting-manager::report.ex_members', ['count' => $exMembers]) }})</small></p>
                            </div>
                            <div class="icon"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ $fmt::hours((int) $totals['minutes']) }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.hours_ratted') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>@rmisk($median)</h3>
                                <p>{{ trans('ratting-manager::dashboard.median_isk_per_hour') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-tachometer-alt"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ number_format($totals['rats']) }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.rats_killed') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-skull-crossbones"></i></div>
                        </div>
                    </div>
                </div>
                @include('ratting-manager::partials.space-counts', ['counts' => $spaceCounts])
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users"></i> {{ trans('ratting-manager::report.members') }}</h3>
                <div class="card-tools">
                    <a class="btn btn-sm btn-rm-secondary" href="{{ route('ratting-manager.report.csv', array_filter(['month' => $pickedMonth, 'corporation' => request('corporation')])) }}">
                        <i class="fas fa-file-csv"></i> {{ trans('ratting-manager::leaderboard.download_csv') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">{{ trans('ratting-manager::report.members_note') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="rm-report-members">
                        <thead>
                            <tr>
                                <th>{{ trans('ratting-manager::leaderboard.rank') }}</th>
                                <th>{{ trans('ratting-manager::dashboard.player') }}</th>
                                <th class="text-right" title="{{ trans('ratting-manager::dashboard.isk_ratted_hint') }}">{{ trans('ratting-manager::dashboard.isk_ratted') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::dashboard.corp_tax') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::report.received') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::dashboard.hours') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::dashboard.isk_per_hour') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::dashboard.rats') }}</th>
                                <th class="text-right">{{ trans('ratting-manager::dashboard.active_days') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                <tr>
                                    <td data-order="{{ $i }}">{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ route('ratting-manager.ratters.show', array_filter(['characterId' => $row['main_id'], 'period' => 'm-' . $pickedMonth])) }}">{{ $row['name'] }}</a>
                                        @if(count($row['characters']) > 1)
                                            <small class="text-muted">(+{{ count($row['characters']) - 1 }})</small>
                                        @endif
                                        @include('ratting-manager::partials.player-tags', ['row' => $row])
                                    </td>
                                    <td class="text-right" data-order="{{ $row['ratted'] }}">@rmisk($row['ratted'])</td>
                                    <td class="text-right" data-order="{{ $row['corp_tax'] }}">@rmisk($row['corp_tax'])</td>
                                    <td class="text-right" data-order="{{ $row['income'] }}">
                                        @if($row['estimated'] > 0)
                                            <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($row['income'])</span>
                                        @else
                                            @rmisk($row['income'])
                                        @endif
                                    </td>
                                    <td class="text-right" data-order="{{ $row['minutes'] }}">{{ $fmt::hours($row['minutes']) }}</td>
                                    <td class="text-right" data-order="{{ $row['isk_per_hour'] }}">@rmisk($row['isk_per_hour'])</td>
                                    <td class="text-right">{{ number_format($row['rats']) }}</td>
                                    <td class="text-right">{{ $row['active_days'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::report.top_systems') }}</h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('ratting-manager::dashboard.system') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::dashboard.isk_ratted') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::report.received') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::dashboard.ticks') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::ratter.characters') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($systems as $system)
                            <tr>
                                <td>{{ $system['name'] }} @if($system['security'] !== null)<small class="text-muted">({{ number_format($system['security'], 1) }})</small>@endif</td>
                                <td class="text-right">@rmisk($system['ratted'])</td>
                                <td class="text-right">@rmisk($system['income'])</td>
                                <td class="text-right">{{ number_format($system['ticks']) }}</td>
                                <td class="text-right">{{ $system['characters'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                        @endforelse
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
    if ($.fn.DataTable && document.getElementById('rm-report-members')) {
        $('#rm-report-members').DataTable({ order: [[0, 'asc']], pageLength: 50 });
    }
});
</script>
@endpush
