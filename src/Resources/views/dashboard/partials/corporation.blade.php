{{-- The Corporation tab, rendered on request and dropped into the dashboard. Directors only.
     Sections, because the whole of it is a very long page and most visits want one part. A
     chart in a closed section has no size, so it is drawn when the section first opens. --}}

<ul class="nav nav-pills rm-subtabs" id="rm-corp-subtabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#rm-corp-pane-overview" role="tab"><i class="fas fa-chart-pie"></i> {{ trans('ratting-manager::dashboard.tab_overview') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#rm-corp-pane-players" role="tab"><i class="fas fa-users"></i> {{ trans('ratting-manager::dashboard.tab_players') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#rm-corp-pane-when" role="tab"><i class="fas fa-clock"></i> {{ trans('ratting-manager::dashboard.tab_when') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#rm-corp-pane-where" role="tab"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::dashboard.tab_where') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#rm-corp-pane-kills" role="tab"><i class="fas fa-skull"></i> {{ trans('ratting-manager::dashboard.tab_kills') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#rm-corp-pane-months" role="tab"><i class="fas fa-calendar-alt"></i> {{ trans('ratting-manager::dashboard.tab_months') }}</a></li>
</ul>

<div class="tab-content rm-subtab-content">
<div class="tab-pane fade show active" id="rm-corp-pane-overview" role="tabpanel">

<div class="row">
    <div class="col-12">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie"></i> {{ $period->label() }}</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-primary">
                            <span class="info-box-icon"><i class="fas fa-crosshairs"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.isk_ratted') }}</span>
                                <span class="info-box-number">@rmisk($totals['ratted'])</span>
                                <small>{{ trans('ratting-manager::dashboard.isk_ratted_hint') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-success">
                            <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.ratting_income') }}</span>
                                <span class="info-box-number">@rmisk($totals['income'])</span>
                                @if($totals['estimated'] > 0)
                                    <small class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">{{ trans('ratting-manager::dashboard.worked_out_share', ['amount' => \RattingManager\Support\Format::isk($totals['estimated'])]) }}</small>
                                @else
                                    <small>{{ trans('ratting-manager::dashboard.ratting_income_hint') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-danger">
                            <span class="info-box-icon"><i class="fas fa-landmark"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.corp_tax') }}</span>
                                <span class="info-box-number">@rmisk($totals['corp_tax'])</span>
                                <small>{{ trans('ratting-manager::dashboard.corp_tax_hint') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-box bg-gradient-info">
                            <span class="info-box-icon"><i class="fas fa-tachometer-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ trans('ratting-manager::dashboard.median_isk_per_hour') }}</span>
                                <span class="info-box-number">@rmisk($median)</span>
                                <small>{{ trans('ratting-manager::dashboard.median_hint') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>{{ $totals['players'] }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.active_players') }} <small>({{ trans('ratting-manager::dashboard.active_characters', ['count' => $totals['characters']]) }})</small></p>
                            </div>
                            <div class="icon"><i class="fas fa-users"></i></div>
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
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>@rmisk($totals['ess'])</h3>
                                <p>{{ trans('ratting-manager::dashboard.ess_payouts') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-university"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ number_format($totals['rats']) }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.rats_killed') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-skull-crossbones"></i></div>
                        </div>
                    </div>
                    <div class="col-xl col-lg-4 col-md-6">
                        <div class="small-box bg-secondary" title="{{ trans('ratting-manager::dashboard.tracked_hint') }}">
                            <div class="inner">
                                <h3>{{ $totals['coverage'] === null ? '-' : $totals['coverage'] . '%' }}</h3>
                                <p>{{ trans('ratting-manager::dashboard.tracked_share') }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-user-check"></i></div>
                        </div>
                    </div>
                </div>
                @include('ratting-manager::partials.space-counts', ['counts' => $spaceCounts])
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('ratting-manager::partials.insights', ['insights' => $insights, 'corporation' => true])
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-bar"></i> {{ trans('ratting-manager::dashboard.income_12_months') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-corp-monthly"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.income_12_months_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie"></i> {{ trans('ratting-manager::dashboard.concentration') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-corp-concentration"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.concentration_note') }}</p></div>
        </div>
    </div>
</div>

</div>

<div class="tab-pane fade" id="rm-corp-pane-players" role="tabpanel">

<div class="row">
    <div class="col-12">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-trophy"></i> {{ trans('ratting-manager::dashboard.leaderboard') }}</h3>
                <div class="card-tools">
                    @include('ratting-manager::partials.view-all', ['count' => count($leaderboard), 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS, 'url' => route('ratting-manager.leaderboard', $listQuery)])
                </div>
            </div>
            <div class="card-body p-0">
                @include('ratting-manager::leaderboard.partials.director-table', ['rows' => $leaderboard, 'ownMain' => null, 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS])
            </div>
        </div>
    </div>
</div>

@include('ratting-manager::dashboard.partials.wallet-gaps', ['rows' => $walletGaps])

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users"></i> {{ trans('ratting-manager::dashboard.active_ratters') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-corp-active"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.active_ratters_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> {{ trans('ratting-manager::dashboard.ratter_movement') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-corp-movement"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.ratter_movement_note') }}</p></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        @include('ratting-manager::dashboard.partials.gone-quiet', ['rows' => $goneQuiet, 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS, 'moreUrl' => $listUrl('gone-quiet')])
    </div>
    <div class="col-lg-6">
        @include('ratting-manager::dashboard.partials.untracked', ['rows' => $untracked, 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS, 'moreUrl' => $listUrl('untracked')])
    </div>
</div>

</div>

<div class="tab-pane fade" id="rm-corp-pane-when" role="tabpanel">

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-line"></i> {{ trans('ratting-manager::dashboard.daily_income') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart"><canvas id="rm-corp-daily"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.daily_income_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-th"></i> {{ trans('ratting-manager::dashboard.when_corp_rats') }}</h3>
            </div>
            <div class="card-body">
                @include('ratting-manager::partials.heatmap', ['heatmap' => $heatmap, 'perPlayer' => true, 'title' => trans('ratting-manager::dashboard.when_corp_rats')])
                <p class="rm-chart-note">{{ trans('ratting-manager::dashboard.when_corp_rats_note') }}</p>
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
                <div class="rm-chart"><canvas id="rm-corp-weekdays"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.time_note') }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-clock"></i> {{ trans('ratting-manager::insights.players_by_hour') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart"><canvas id="rm-corp-hours"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.players_by_hour_note') }}</p>
            </div>
        </div>
    </div>
</div>

</div>

<div class="tab-pane fade" id="rm-corp-pane-where" role="tabpanel">

<div class="row">
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::dashboard.systems') }}</h3>
            </div>
            <div class="card-body"><div class="rm-chart-tall"><canvas id="rm-corp-systems"></canvas></div><p class="rm-chart-note">{{ trans('ratting-manager::dashboard.systems_note') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt"></i> {{ trans('ratting-manager::insights.efficiency') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart-tall"><canvas id="rm-corp-efficiency"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.efficiency_note') }} {{ trans('ratting-manager::insights.efficiency_top', ['count' => \RattingManager\Http\Controllers\DashboardController::EFFICIENCY_SYSTEMS_CORPORATION]) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('ratting-manager::partials.systems-table', ['rows' => $insights['systems'], 'corporation' => true, 'limit' => \RattingManager\Http\Controllers\ListController::DASHBOARD_ROWS, 'moreUrl' => $listUrl('systems')])
    </div>
</div>

</div>

<div class="tab-pane fade" id="rm-corp-pane-kills" role="tabpanel">

<div class="row">
    <div class="col-lg-8">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-skull"></i> {{ trans('ratting-manager::dashboard.what_we_kill') }}</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ trans('ratting-manager::dashboard.by_faction') }}</h6>
                        <div class="rm-chart" style="height: 260px;"><canvas id="rm-corp-factions"></canvas></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">{{ trans('ratting-manager::dashboard.by_size') }}</h6>
                        <div class="rm-chart" style="height: 260px;"><canvas id="rm-corp-sizes"></canvas></div>
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
    <div class="col-12">
        @include('ratting-manager::dashboard.partials.spawns', ['spawns' => $spawns, 'total' => $spawnTotal, 'showCharacter' => true, 'moreUrl' => $listUrl('spawns')])
    </div>
</div>

</div>

<div class="tab-pane fade" id="rm-corp-pane-months" role="tabpanel">

<div class="row">
    <div class="col-12">
        @include('ratting-manager::dashboard.partials.corporation-months', ['months' => $months])
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-landmark"></i> {{ trans('ratting-manager::insights.earned_and_taxed') }}</h3>
            </div>
            <div class="card-body">
                <div class="rm-chart"><canvas id="rm-corp-months"></canvas></div>
                <p class="rm-chart-note">{{ trans('ratting-manager::insights.earned_and_taxed_note') }}</p>
            </div>
        </div>
    </div>
</div>

</div>

</div>
