{{--
    Every system ratted in the period, most hours first. $rows are
    Insights systems rows; $corporation adds how many players ratted there
    and who ratted there most.
--}}
@php
    $corporation = $corporation ?? false;
    $limit = $limit ?? null;
    $count = count($rows);
    $rows = $limit === null ? $rows : array_slice($rows, 0, $limit);
    $fmt = \RattingManager\Support\Format::class;
    $people = $corporation ? app(\RattingManager\Services\Character\Players::class) : null;
@endphp

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-globe"></i> {{ trans('ratting-manager::insights.systems_title') }}</h3>
        <div class="card-tools">
            <small class="text-muted mr-2">{{ trans('ratting-manager::insights.efficiency_note') }}</small>
            @include('ratting-manager::partials.view-all', ['count' => $count, 'limit' => $limit, 'url' => $moreUrl ?? null])
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0 rm-systems-table" @if(!empty($tableId)) id="{{ $tableId }}" @endif>
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::dashboard.system') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.hours') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.share') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.income') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.isk_per_hour') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.rats') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.days_col') }}</th>
                    @if($corporation)
                        <th class="text-right">{{ trans('ratting-manager::insights.players') }}</th>
                        <th>{{ trans('ratting-manager::insights.top_ratter') }}</th>
                    @endif
                    <th>{{ trans('ratting-manager::insights.last') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $s)
                    <tr>
                        <td>
                            {{ $s['name'] }}
                            @if($s['security'] !== null)
                                <small class="text-muted">{{ number_format($s['security'], 1) }}</small>
                            @endif
                        </td>
                        <td class="text-right" data-order="{{ $s['minutes'] }}">{{ $fmt::hours($s['minutes']) }}</td>
                        <td class="text-right" data-order="{{ $s['hours_share'] }}">
                            <span class="rm-share-bar" style="--rm-share: {{ min(100, $s['hours_share']) }}%;">{{ number_format($s['hours_share'], 1) }}%</span>
                        </td>
                        <td class="text-right" data-order="{{ $s['income'] }}">@rmisk($s['income'])</td>
                        <td class="text-right" data-order="{{ $s['isk_per_hour'] }}">{{ $s['minutes'] > 0 ? $fmt::isk($s['isk_per_hour']) : '-' }}</td>
                        <td class="text-right">{{ number_format($s['rats']) }}</td>
                        <td class="text-right">{{ $s['active_days'] }}</td>
                        @if($corporation)
                            <td class="text-right">{{ $s['players'] }}</td>
                            <td>
                                @if($s['top_main'])
                                    <a href="{{ route('ratting-manager.ratters.show', $s['top_main']) }}">{{ $people->name($s['top_main']) }}</a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @if($s['last'])
                                <span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $s['last']) }}Z">{{ substr($s['last'], 0, 16) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $corporation ? 10 : 8 }}" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
