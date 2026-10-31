{{-- Every column, for directors: the Corporation tab shows the top of it, the Leaderboard page all of it. --}}
@php $shown = isset($limit) ? array_slice($rows, 0, $limit) : $rows; @endphp
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 rm-leaderboard">
        <thead>
            <tr>
                <th>{{ trans('ratting-manager::leaderboard.rank') }}</th>
                <th>{{ trans('ratting-manager::dashboard.player') }}</th>
                <th class="text-right" title="{{ trans('ratting-manager::dashboard.isk_ratted_hint') }}">{{ trans('ratting-manager::dashboard.isk_ratted') }}</th>
                <th class="text-right">{{ trans('ratting-manager::dashboard.income') }}</th>
                <th class="text-right">{{ trans('ratting-manager::dashboard.hours') }}</th>
                <th class="text-right">{{ trans('ratting-manager::dashboard.isk_per_hour') }}</th>
                <th class="text-right" title="{{ trans('ratting-manager::dashboard.vs_median_title') }}">{{ trans('ratting-manager::dashboard.vs_median') }}</th>
                <th class="text-right">{{ trans('ratting-manager::dashboard.rats') }}</th>
                <th class="text-right">{{ trans('ratting-manager::dashboard.active_days') }}</th>
                <th class="text-right" title="{{ trans('ratting-manager::dashboard.change_title') }}">{{ trans('ratting-manager::dashboard.change') }}</th>
                <th>{{ trans('ratting-manager::dashboard.coverage') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shown as $i => $row)
                <tr class="{{ ($ownMain ?? null) === $row['main_id'] ? 'rm-own-row' : '' }}">
                    <td>
                        @if($i === 0)
                            <span class="badge badge-gold">1</span>
                        @elseif($i === 1)
                            <span class="badge badge-silver">2</span>
                        @elseif($i === 2)
                            <span class="badge badge-bronze">3</span>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('ratting-manager.ratters.show', $row['main_id']) }}">{{ $row['name'] }}</a>
                        @if(count($row['characters']) > 1)
                            <small class="text-muted">(+{{ count($row['characters']) - 1 }})</small>
                        @endif
                        @include('ratting-manager::partials.player-tags', ['row' => $row])
                    </td>
                    <td class="text-right">@rmisk($row['ratted'])</td>
                    <td class="text-right">
                        @if($row['estimated'] > 0)
                            <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($row['income'])</span>
                        @else
                            @rmisk($row['income'])
                        @endif
                    </td>
                    <td class="text-right">{{ \RattingManager\Support\Format::hours($row['minutes']) }}</td>
                    <td class="text-right">@rmisk($row['isk_per_hour'])</td>
                    <td class="text-right">
                        @if($row['efficiency'] === null)
                            <span class="text-muted">-</span>
                        @elseif($row['efficiency'] >= 100)
                            <span class="rm-efficiency-up"><i class="fas fa-arrow-up"></i> {{ $row['efficiency'] }}%</span>
                        @else
                            <span class="rm-efficiency-down"><i class="fas fa-arrow-down"></i> {{ $row['efficiency'] }}%</span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($row['rats']) }}</td>
                    <td class="text-right">{{ $row['active_days'] }}</td>
                    <td class="text-right">
                        @if($row['change'] === null)
                            <span class="text-muted">-</span>
                        @elseif($row['change'] >= 0)
                            <span class="rm-efficiency-up"><i class="fas fa-caret-up"></i> {{ $row['change'] }}%</span>
                        @else
                            <span class="rm-efficiency-down"><i class="fas fa-caret-down"></i> {{ abs($row['change']) }}%</span>
                        @endif
                    </td>
                    <td>@include('ratting-manager::partials.coverage-badge', ['coverage' => $row['coverage']])</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
