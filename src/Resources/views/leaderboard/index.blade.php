@extends('ratting-manager::layouts.page')

@section('title', trans('ratting-manager::leaderboard.title'))
@section('page_header', trans('ratting-manager::leaderboard.title'))

@section('rm-tabs')
    @include('ratting-manager::partials.page-tabs', ['icon' => 'fa-trophy', 'label' => trans('ratting-manager::menu.leaderboard')])
@endsection

@section('rm-body')
    @if($notConfigured)
        @include('ratting-manager::partials.not-configured')
    @elseif($isDirector)
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-trophy"></i> {{ trans('ratting-manager::leaderboard.full_board') }} - {{ $period->label() }}</h3>
                <div class="card-tools">
                    @include('ratting-manager::partials.period-picker', ['corporations' => $corporations, 'pickedCorporation' => $pickedCorporation])
                </div>
            </div>
            <div class="card-body p-0">
                @include('ratting-manager::leaderboard.partials.director-table', ['rows' => $rows, 'ownMain' => $ownMain])
            </div>
            <div class="card-footer text-muted small d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px">
                <span>{{ trans('ratting-manager::dashboard.median_isk_per_hour') }}: @rmisk($median) ({{ trans('ratting-manager::dashboard.median_hint') }})</span>
                <a class="btn btn-sm btn-rm-secondary" href="{{ route('ratting-manager.leaderboard.csv', array_filter(['period' => $period->key, 'corporation' => $pickedCorporation])) }}">
                    <i class="fas fa-file-csv"></i> {{ trans('ratting-manager::leaderboard.download_csv') }}
                </a>
            </div>
        </div>
    @else
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-trophy"></i> {{ trans('ratting-manager::leaderboard.hours_board') }} - {{ $period->label() }}</h3>
                <div class="card-tools">
                    @include('ratting-manager::partials.period-picker')
                </div>
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-2">
                    {{ trans('ratting-manager::leaderboard.members_note') }}
                    @if($ownRank)
                        <strong>{{ trans('ratting-manager::leaderboard.your_rank', ['rank' => $ownRank, 'count' => $playerCount]) }}</strong>
                    @else
                        {{ trans('ratting-manager::leaderboard.not_ratted') }}
                    @endif
                </p>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('ratting-manager::leaderboard.rank') }}</th>
                            <th>{{ trans('ratting-manager::dashboard.player') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::dashboard.hours') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::dashboard.active_days') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="{{ $row['main_id'] === $ownMain ? 'rm-own-row' : '' }}">
                                <td>
                                    @if($row['rank'] === 1)
                                        <span class="badge badge-gold">1</span>
                                    @elseif($row['rank'] === 2)
                                        <span class="badge badge-silver">2</span>
                                    @elseif($row['rank'] === 3)
                                        <span class="badge badge-bronze">3</span>
                                    @else
                                        {{ $row['rank'] }}
                                    @endif
                                </td>
                                <td>
                                    {{ $row['name'] }}
                                    @include('ratting-manager::partials.player-tags', ['row' => $row])
                                </td>
                                <td class="text-right">{{ \RattingManager\Support\Format::hours($row['minutes']) }}</td>
                                <td class="text-right">{{ $row['active_days'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
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

