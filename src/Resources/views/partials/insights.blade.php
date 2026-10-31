{{--
    The insight tiles: where, when and how well. $insights comes from
    Insights::for(); $corporation switches the player-count tiles on in place
    of the streaks.
--}}
@php
    $h = $insights['highlights'];
    $corporation = $corporation ?? false;
    $fmt = \RattingManager\Support\Format::class;
@endphp

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-lightbulb"></i> {{ trans('ratting-manager::insights.title') }}</h3>
        <div class="card-tools"><small class="text-muted">{{ trans('ratting-manager::insights.title_hint') }}</small></div>
    </div>
    <div class="card-body">
        <div class="rm-insights">
            <div class="rm-insight {{ $h['favourite_system'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::insights.favourite_system') }}</div>
                @if($h['favourite_system'])
                    <div class="rm-insight-value" title="{{ $h['favourite_system']['name'] }}">{{ $h['favourite_system']['name'] }}</div>
                    <div class="rm-insight-sub">{{ $fmt::hours($h['favourite_system']['minutes']) }}, {{ trans('ratting-manager::insights.of_hours', ['share' => $h['favourite_system']['hours_share']]) }}</div>
                @else
                    <div class="rm-insight-value">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['best_system'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-bolt"></i> {{ trans('ratting-manager::insights.best_system') }}</div>
                @if($h['best_system'])
                    <div class="rm-insight-value" title="{{ $h['best_system']['name'] }}">{{ $h['best_system']['name'] }}</div>
                    <div class="rm-insight-sub">@rmisk($h['best_system']['isk_per_hour']) {{ trans('ratting-manager::insights.per_hour') }}</div>
                @else
                    <div class="rm-insight-value" title="{{ trans('ratting-manager::insights.min_hour_note') }}">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['busiest_weekday'] && $h['busiest_weekday']['minutes'] > 0 ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-calendar-day"></i> {{ trans('ratting-manager::insights.busiest_weekday') }}</div>
                @if($h['busiest_weekday'] && $h['busiest_weekday']['minutes'] > 0)
                    <div class="rm-insight-value">{{ $h['busiest_weekday']['name'] }}</div>
                    <div class="rm-insight-sub">{{ $fmt::hours($h['busiest_weekday']['minutes']) }}, {{ trans('ratting-manager::insights.days', ['count' => $h['busiest_weekday']['active_days']]) }}</div>
                @else
                    <div class="rm-insight-value">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['best_weekday'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-calendar-check"></i> {{ trans('ratting-manager::insights.best_weekday') }}</div>
                @if($h['best_weekday'])
                    <div class="rm-insight-value">{{ $h['best_weekday']['name'] }}</div>
                    <div class="rm-insight-sub">@rmisk($h['best_weekday']['isk_per_hour']) {{ trans('ratting-manager::insights.per_hour') }}</div>
                @else
                    <div class="rm-insight-value" title="{{ trans('ratting-manager::insights.min_hour_note') }}">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['busiest_window'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-clock"></i> {{ trans('ratting-manager::insights.busiest_window') }}</div>
                @if($h['busiest_window'])
                    <div class="rm-insight-value">{{ $h['busiest_window']['label'] }} <small class="text-muted">{{ trans('ratting-manager::insights.eve') }}</small></div>
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.of_hours', ['share' => $insights['totals']['minutes'] > 0 ? round($h['busiest_window']['minutes'] / $insights['totals']['minutes'] * 100) : 0]) }}</div>
                @else
                    <div class="rm-insight-value">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['best_window'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-hourglass-half"></i> {{ trans('ratting-manager::insights.best_window') }}</div>
                @if($h['best_window'])
                    <div class="rm-insight-value">{{ $h['best_window']['label'] }} <small class="text-muted">{{ trans('ratting-manager::insights.eve') }}</small></div>
                    <div class="rm-insight-sub">@rmisk($h['best_window']['isk_per_hour']) {{ trans('ratting-manager::insights.per_hour') }}</div>
                @else
                    <div class="rm-insight-value" title="{{ trans('ratting-manager::insights.min_hour_note') }}">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            @if($corporation)
                <div class="rm-insight {{ $h['peak_concurrent'] ? '' : 'rm-insight-empty' }}">
                    <div class="rm-insight-label"><i class="fas fa-users"></i> {{ trans('ratting-manager::insights.peak_concurrent') }}</div>
                    @if($h['peak_concurrent'])
                        <div class="rm-insight-value">{{ $h['peak_concurrent']['players'] }}</div>
                        <div class="rm-insight-sub">{{ trans('ratting-manager::insights.peak_concurrent_hint', ['when' => $h['peak_concurrent']['when']]) }}</div>
                    @else
                        <div class="rm-insight-value">{{ trans('ratting-manager::insights.nothing') }}</div>
                    @endif
                </div>

                <div class="rm-insight">
                    <div class="rm-insight-label"><i class="fas fa-user-friends"></i> {{ trans('ratting-manager::insights.players_per_day') }}</div>
                    <div class="rm-insight-value">{{ number_format($h['players_per_day'], 1) }}</div>
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.days', ['count' => $h['active_days']]) }}</div>
                </div>

                <div class="rm-insight">
                    <div class="rm-insight-label"><i class="fas fa-user-tag"></i> {{ trans('ratting-manager::insights.income_per_player') }}</div>
                    <div class="rm-insight-value">@rmisk($h['income_per_player'])</div>
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.income_per_player_hint') }}</div>
                </div>
            @else
                <div class="rm-insight">
                    <div class="rm-insight-label"><i class="fas fa-fire"></i> {{ trans('ratting-manager::insights.longest_streak') }}</div>
                    <div class="rm-insight-value">{{ trans('ratting-manager::insights.days', ['count' => $h['longest_streak']]) }}</div>
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.current_streak') }}: {{ trans('ratting-manager::insights.days', ['count' => $h['current_streak']]) }}</div>
                </div>
            @endif

            <div class="rm-insight">
                <div class="rm-insight-label"><i class="fas fa-coins"></i> {{ trans('ratting-manager::insights.income_per_day') }}</div>
                <div class="rm-insight-value">@rmisk($h['income_per_active_day'])</div>
                <div class="rm-insight-sub">{{ trans('ratting-manager::insights.days', ['count' => $h['active_days']]) }}</div>
            </div>

            <div class="rm-insight {{ $h['sessions']['count'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-stopwatch"></i> {{ trans('ratting-manager::insights.sessions') }}</div>
                <div class="rm-insight-value">{{ number_format($h['sessions']['count']) }}</div>
                @if($h['sessions']['count'])
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.typical_session', ['time' => $fmt::hours($h['sessions']['median_minutes']), 'longest' => $fmt::hours($h['sessions']['longest_minutes'])]) }}</div>
                @endif
            </div>

            <div class="rm-insight">
                <div class="rm-insight-label"><i class="fas fa-skull-crossbones"></i> {{ trans('ratting-manager::insights.rats_per_hour') }}</div>
                <div class="rm-insight-value">{{ number_format($h['rats_per_hour'], 1) }}</div>
                <div class="rm-insight-sub">{{ trans('ratting-manager::insights.killed', ['count' => number_format($insights['totals']['rats'])]) }}</div>
            </div>

            <div class="rm-insight {{ $h['favourite_faction'] ? '' : 'rm-insight-empty' }}">
                <div class="rm-insight-label"><i class="fas fa-crosshairs"></i> {{ trans('ratting-manager::insights.favourite_faction') }}</div>
                @if($h['favourite_faction'])
                    <div class="rm-insight-value">{{ $h['favourite_faction']['name'] }}</div>
                    <div class="rm-insight-sub">{{ trans('ratting-manager::insights.killed', ['count' => number_format($h['favourite_faction']['killed'])]) }}</div>
                @else
                    <div class="rm-insight-value">{{ trans('ratting-manager::insights.nothing') }}</div>
                @endif
            </div>

            <div class="rm-insight {{ $h['ess_share'] === null ? 'rm-insight-empty' : '' }}">
                <div class="rm-insight-label"><i class="fas fa-university"></i> {{ trans('ratting-manager::insights.ess_share') }}</div>
                <div class="rm-insight-value">{{ $h['ess_share'] === null ? '-' : $h['ess_share'] . '%' }}</div>
                <div class="rm-insight-sub">{{ trans('ratting-manager::insights.ess_share_hint') }}</div>
            </div>
        </div>
    </div>
</div>
