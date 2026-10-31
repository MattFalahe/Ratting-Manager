{{-- Ratting sessions, newest first. On a dashboard $total says how many the period holds, for the View all link. --}}
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> {{ trans('ratting-manager::dashboard.recent_sessions') }}</h3>
        <div class="card-tools">
            @include('ratting-manager::partials.view-all', ['count' => $total ?? count($rows), 'limit' => count($rows), 'url' => $moreUrl ?? null])
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0" @if(!empty($tableId)) id="{{ $tableId }}" @endif>
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::dashboard.started') }}</th>
                    <th>{{ trans('ratting-manager::common.character') }}</th>
                    <th>{{ trans('ratting-manager::dashboard.system') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.duration') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.ticks') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.rats') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.net_bounty') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.isk_per_hour') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $s)
                    <tr>
                        <td data-order="{{ $s['started_at'] }}"><span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $s['started_at']) }}Z">{{ substr($s['started_at'], 0, 16) }}</span></td>
                        <td>{{ $s['character'] }}</td>
                        <td>
                            {{ $s['system'] }}
                            @if($s['system_count'] > 1)
                                <small class="text-muted">{{ trans('ratting-manager::dashboard.more_systems', ['count' => $s['system_count'] - 1]) }}</small>
                            @endif
                        </td>
                        <td class="text-right" data-order="{{ $s['minutes'] }}">{{ \RattingManager\Support\Format::hours($s['minutes']) }}</td>
                        <td class="text-right">{{ $s['ticks'] }}</td>
                        <td class="text-right">{{ number_format($s['rats']) }}</td>
                        <td class="text-right">
                            @if($s['estimated'])
                                <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($s['net'])</span>
                            @else
                                @rmisk($s['net'])
                            @endif
                        </td>
                        <td class="text-right" data-order="{{ $s['isk_per_hour'] }}">@rmisk($s['isk_per_hour'])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
