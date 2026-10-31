{{-- Ratters whose wallet token has gone, soonest to run out first. Shown only when there are any. --}}
@if(!empty($rows))
    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-key"></i> {{ trans('ratting-manager::dashboard.wallet_gaps') }}</h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::dashboard.wallet_gaps_hint') }}</p>
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('ratting-manager::common.character') }}</th>
                        <th>{{ trans('ratting-manager::dashboard.wallet_gap_state') }}</th>
                        <th>{{ trans('ratting-manager::dashboard.wallet_gap_since') }}</th>
                        <th class="text-right">{{ trans('ratting-manager::dashboard.ticks') }}</th>
                        <th class="text-right">{{ trans('ratting-manager::dashboard.worked_out_income') }}</th>
                        <th>{{ trans('ratting-manager::dashboard.wallet_gap_fix_by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $gap)
                        <tr>
                            <td><a href="{{ route('ratting-manager.ratters.show', $gap['character_id']) }}">{{ $gap['name'] }}</a></td>
                            <td>{{ trans('ratting-manager::dashboard.wallet_gap_state_' . $gap['state']) }}</td>
                            <td>{{ substr($gap['since'], 0, 16) }}</td>
                            <td class="text-right">{{ number_format($gap['ticks']) }}</td>
                            <td class="text-right"><span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($gap['worked_out'])</span></td>
                            <td>
                                @if($gap['days_left'] > 0)
                                    <span class="{{ $gap['days_left'] <= 7 ? 'text-warning' : '' }}">{{ substr($gap['fix_by'], 0, 10) }} ({{ trans('ratting-manager::dashboard.wallet_gap_days', ['count' => $gap['days_left']]) }})</span>
                                @else
                                    <span class="text-danger">{{ trans('ratting-manager::dashboard.wallet_gap_late') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
