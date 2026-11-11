{{--
    The corporation's last 12 months, newest first: what its players earned,
    what landed in the corporation's wallet, who ratted and how well.
--}}
@php
    $fmt = \RattingManager\Support\Format::class;
    // Newest first; the months before the first ratting are left out.
    $active = array_keys(array_filter($months, fn ($m) => $m['income'] > 0 || $m['corp_tax'] > 0 || $m['players'] > 0));
    $months = $active === [] ? [] : array_slice($months, 0, max($active) + 1);
    $sum = fn ($field) => array_sum(array_column($months, $field));
    $income = $sum('income');
    $tax = $sum('corp_tax');
    $minutes = $sum('minutes');
@endphp

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> {{ trans('ratting-manager::insights.corp_months_title') }}</h3>
    </div>
    <div class="card-body p-0 table-responsive">
        <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::insights.corp_months_hint') }}</p>
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::insights.month') }}</th>
                    <th class="text-right" title="{{ trans('ratting-manager::dashboard.isk_ratted_hint') }}">{{ trans('ratting-manager::dashboard.isk_ratted') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.earned') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.ess') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.tax_landed') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.tax_rate') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.players') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::ratter.characters') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.hours') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.isk_per_hour') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.per_player') }}</th>
                </tr>
            </thead>
            <tbody>
                @if($months === [])
                    <tr><td colspan="11" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                @endif
                @foreach($months as $m)
                    @if($m['income'] > 0 || $m['corp_tax'] > 0 || $m['players'] > 0)
                        <tr>
                            <td>{{ $m['month'] }}</td>
                            <td class="text-right">@rmisk($m['income'] + $m['corp_tax'])</td>
                            <td class="text-right">
                                @if($m['estimated'] > 0)
                                    <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($m['income'])</span>
                                @else
                                    @rmisk($m['income'])
                                @endif
                            </td>
                            <td class="text-right">@rmisk($m['ess'])</td>
                            <td class="text-right">@rmisk($m['corp_tax'])</td>
                            <td class="text-right">{{ $m['tax_share'] === null ? '-' : number_format($m['tax_share'], 1) . '%' }}</td>
                            <td class="text-right">{{ $m['players'] }}</td>
                            <td class="text-right">{{ $m['characters'] }}</td>
                            <td class="text-right">{{ $fmt::hours($m['minutes']) }}</td>
                            <td class="text-right">{{ $m['minutes'] > 0 ? $fmt::isk($m['isk_per_hour']) : '-' }}</td>
                            <td class="text-right">{{ $m['players'] > 0 ? $fmt::isk($m['income_per_player']) : '-' }}</td>
                        </tr>
                    @else
                        <tr class="text-muted">
                            <td>{{ $m['month'] }}</td>
                            <td colspan="10" class="text-center">{{ trans('ratting-manager::insights.no_ratting') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            @if(count($months) > 1)
            <tfoot>
                <tr class="rm-total-row">
                    <td>{{ trans('ratting-manager::insights.all_months', ['count' => count($months)]) }}</td>
                    <td class="text-right">@rmisk($income + $tax)</td>
                    <td class="text-right">@rmisk($income)</td>
                    <td class="text-right">@rmisk($sum('ess'))</td>
                    <td class="text-right">@rmisk($tax)</td>
                    <td class="text-right">{{ ($income + $tax) > 0 ? number_format($tax / ($income + $tax) * 100, 1) . '%' : '-' }}</td>
                    <td class="text-right">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ $fmt::hours($minutes) }}</td>
                    <td class="text-right">{{ $minutes > 0 ? $fmt::isk($income / ($minutes / 60)) : '-' }}</td>
                    <td class="text-right">-</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
