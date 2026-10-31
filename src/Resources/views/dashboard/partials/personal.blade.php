{{--
    My Ratting: one player's figures. Used on the dashboard for the signed-in
    member, and on the ratter page for any player a director opens.
--}}
@php
    $workedOut = $totals['estimated'] ?? 0;
    // The corporation's cut as a share of everything the ratting paid out.
    $paidOut = $totals['income'] + $totals['corp_tax'];
    $taxShare = $totals['corp_tax'] > 0 && $paidOut > 0 ? number_format($totals['corp_tax'] / $paidOut * 100, 1) : null;
@endphp

<div class="row">
    <div class="col-12">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-crosshairs"></i>
                    {{ $heading ?? trans('ratting-manager::dashboard.my_ratting') }} - {{ $period->label() }}
                </h3>
                <div class="card-tools">
                    @include('ratting-manager::partials.period-picker', [
                        'characterOptions' => $characterOptions ?? [],
                        'pickedCharacter' => $pickedCharacter ?? null,
                    ])
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-success">
                            <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.ratting_income') }}</span>
                                <span class="info-box-number">@rmisk($totals['income'])</span>
                                @if($workedOut > 0)
                                    <small class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">{{ trans('ratting-manager::dashboard.worked_out_share', ['amount' => \RattingManager\Support\Format::isk($workedOut)]) }}</small>
                                @else
                                    <small>{{ trans('ratting-manager::dashboard.ratting_income_hint') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-info">
                            <span class="info-box-icon"><i class="fas fa-tachometer-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.isk_per_hour') }}</span>
                                <span class="info-box-number">@rmisk($totals['isk_per_hour'])</span>
                                <small>{{ trans('ratting-manager::dashboard.isk_per_hour_hint', ['time' => \RattingManager\Support\Format::hours((int) $totals['minutes'])]) }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-warning">
                            <span class="info-box-icon"><i class="fas fa-skull-crossbones"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.rats_killed') }}</span>
                                <span class="info-box-number">{{ number_format($totals['rats']) }}</span>
                                <small>{{ trans('ratting-manager::dashboard.rats_killed_hint') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-primary">
                            <span class="info-box-icon"><i class="fas fa-trophy"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.corp_rank') }}</span>
                                @if(isset($ownRank) && $ownRank)
                                    <span class="info-box-number">#{{ $ownRank }}</span>
                                    <small>{{ trans('ratting-manager::dashboard.corp_rank_of', ['count' => $rankedPlayers]) }}</small>
                                @else
                                    <span class="info-box-number">-</span>
                                    <small>{{ trans('ratting-manager::dashboard.not_ranked') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>@rmisk($totals['bounty'])</h3>
                                <p>{{ trans('ratting-manager::dashboard.net_bounty') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-crosshairs"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>@rmisk($totals['ess'])</h3>
                                <p>{{ trans('ratting-manager::dashboard.ess_payouts') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-university"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3>@rmisk($totals['corp_tax'])</h3>
                                <p>
                                    {{ trans('ratting-manager::dashboard.tax_paid') }}
                                    @if($taxShare !== null)
                                        <small class="d-block">{{ trans('ratting-manager::dashboard.tax_paid_hint', ['rate' => $taxShare]) }}</small>
                                    @endif
                                </p>
                            </div>
                            <div class="icon"><i class="fas fa-landmark"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>{{ \RattingManager\Support\Format::hours((int) $totals['minutes']) }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.hours_ratted') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $totals['active_days'] }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.active_days') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('ratting-manager::partials.insights', ['insights' => $insights, 'corporation' => false])
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar"></i> {{ trans('ratting-manager::dashboard.income_12_months') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-chart-monthly"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.income_12_months_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-medal"></i> {{ trans('ratting-manager::dashboard.records') }}</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach(['best_tick', 'best_session', 'best_day', 'best_month'] as $record)
                            <tr>
                                <td>{{ trans('ratting-manager::dashboard.' . $record) }}</td>
                                @if($records[$record])
                                    <td class="text-right">
                                        <strong>@rmisk($records[$record]['value'])</strong><br>
                                        <small class="text-muted">{{ $records[$record]['when'] }}</small>
                                    </td>
                                @else
                                    <td class="text-right text-muted">-</td>
                                @endif
                            </tr>
                        @endforeach
                        <tr>
                            <td>{{ trans('ratting-manager::dashboard.longest_session') }}</td>
                            @if($records['longest_session'])
                                <td class="text-right">
                                    <strong>{{ \RattingManager\Support\Format::hours($records['longest_session']['minutes']) }}</strong><br>
                                    <small class="text-muted">{{ $records['longest_session']['when'] }}</small>
                                </td>
                            @else
                                <td class="text-right text-muted">-</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> {{ trans('ratting-manager::dashboard.daily_income') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-chart-daily"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.daily_income_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-th"></i> {{ trans('ratting-manager::dashboard.when_i_rat') }}</h3>
            </div>
            <div class="card-body">
                @include('ratting-manager::partials.heatmap', ['heatmap' => $heatmap, 'perPlayer' => false, 'title' => trans('ratting-manager::dashboard.when_i_rat')])
                <p class="rm-chart-note">{{ trans('ratting-manager::dashboard.when_i_rat_note') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-week"></i> {{ trans('ratting-manager::insights.by_weekday') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart"><canvas id="rm-chart-weekdays"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.time_note') }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock"></i> {{ trans('ratting-manager::insights.by_hour') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart"><canvas id="rm-chart-hours"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.time_note') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::dashboard.where_i_rat') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-chart-systems"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.systems_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> {{ trans('ratting-manager::insights.efficiency') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart"><canvas id="rm-chart-efficiency"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.efficiency_note') }} {{ trans('ratting-manager::insights.efficiency_top', ['count' => \RattingManager\Http\Controllers\DashboardController::EFFICIENCY_SYSTEMS_PERSONAL]) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('ratting-manager::partials.systems-table', ['rows' => $insights['systems'], 'corporation' => false, 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS, 'moreUrl' => isset($listUrl) ? $listUrl('systems') : null])
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-skull"></i> {{ trans('ratting-manager::dashboard.what_i_kill') }}</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ trans('ratting-manager::dashboard.by_faction') }}</h6>
                        <div class="rm-chart" style="height: 260px;"><canvas id="rm-chart-factions"></canvas></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ trans('ratting-manager::dashboard.by_size') }}</h6>
                        <div class="rm-chart" style="height: 260px;"><canvas id="rm-chart-sizes"></canvas></div>
                    </div>
                </div>
                <p class="rm-chart-note">{{ trans('ratting-manager::dashboard.kills_note') }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @include('ratting-manager::partials.npc-table', ['npcs' => $insights['npcs']])
    </div>
</div>

<div class="row">
    <div class="{{ count($personalCharts['characters']['labels']) > 1 ? 'col-lg-6' : 'col-12' }}">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-stopwatch"></i> {{ trans('ratting-manager::dashboard.session_isk_per_hour') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-chart-sessions"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.session_isk_per_hour_note') }}</p></div>
        </div>
    </div>
    @if(count($personalCharts['characters']['labels']) > 1)
        <div class="col-lg-6">
            <div class="card card-dark">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users"></i> {{ trans('ratting-manager::dashboard.by_character') }}</h3>
                </div>
                <div class="card-body"><div class="rm-chart"><canvas id="rm-chart-characters"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.by_character_note') }}</p></div>
            </div>
        </div>
    @endif
</div>

<div class="row">
    <div class="col-12">
        @include('ratting-manager::dashboard.partials.month-by-character')
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        @include('ratting-manager::dashboard.partials.sessions-table', [
            'rows' => $sessions['list'],
            'total' => $sessions['total'],
            'moreUrl' => isset($listUrl) ? $listUrl('sessions') : null,
        ])
    </div>
    <div class="col-lg-4">
        @include('ratting-manager::dashboard.partials.spawns', ['spawns' => $spawns, 'total' => $spawnTotal, 'showCharacter' => true, 'moreUrl' => isset($listUrl) ? $listUrl('spawns') : null])
    </div>
</div>
