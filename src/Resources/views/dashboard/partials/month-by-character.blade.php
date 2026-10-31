{{--
    What each character earned in one month, with the player's total, and
    the last 12 months by character. The month picker keeps the period and
    character picked above, so only the month changes.
--}}
@php
    $fmt = \RattingManager\Support\Format::class;
    $b = $monthBreakdown;
@endphp

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> {{ trans('ratting-manager::insights.month_title') }} - {{ $b['month'] }}</h3>
        <div class="card-tools">
            <form method="GET" action="{{ url()->current() }}" class="rm-filters">
                <input type="hidden" name="period" value="{{ $period->key }}">
                @if(!empty($pickedCharacter))
                    <input type="hidden" name="character" value="{{ $pickedCharacter }}">
                @endif
                <select name="month" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::insights.month') }}">
                    @foreach($monthChoices as $key => $label)
                        <option value="{{ $key }}" @if($pickedMonth === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-rm-primary">{{ trans('ratting-manager::insights.show_month') }}</button></noscript>
            </form>
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::insights.month_hint') }}</p>
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::common.character') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.bounties') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.ess') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.income') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.corp_tax') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.hours') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.isk_per_hour') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.ticks') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.rats') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($b['rows'] as $r)
                    <tr>
                        <td>{{ $r['name'] }}</td>
                        <td class="text-right">@rmisk($r['bounty'])</td>
                        <td class="text-right">@rmisk($r['ess'])</td>
                        <td class="text-right">
                            @if($r['estimated'] > 0)
                                <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($r['income'])</span>
                            @else
                                @rmisk($r['income'])
                            @endif
                        </td>
                        <td class="text-right">@rmisk($r['corp_tax'])</td>
                        <td class="text-right">{{ $fmt::hours($r['minutes']) }}</td>
                        <td class="text-right">{{ $r['minutes'] > 0 ? $fmt::isk($r['isk_per_hour']) : '-' }}</td>
                        <td class="text-right">{{ number_format($r['ticks']) }}</td>
                        <td class="text-right">{{ number_format($r['rats']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                @endforelse
            </tbody>
            @if(count($b['rows']) > 0)
                <tfoot>
                    <tr class="rm-total-row">
                        <td>{{ trans('ratting-manager::insights.total') }}</td>
                        <td class="text-right">@rmisk($b['total']['bounty'])</td>
                        <td class="text-right">@rmisk($b['total']['ess'])</td>
                        <td class="text-right">
                            @if($b['total']['estimated'] > 0)
                                <span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($b['total']['income'])</span>
                            @else
                                @rmisk($b['total']['income'])
                            @endif
                        </td>
                        <td class="text-right">@rmisk($b['total']['corp_tax'])</td>
                        <td class="text-right">{{ $fmt::hours($b['total']['minutes']) }}</td>
                        <td class="text-right">{{ $b['total']['minutes'] > 0 ? $fmt::isk($b['total']['isk_per_hour']) : '-' }}</td>
                        <td class="text-right">{{ number_format($b['total']['ticks']) }}</td>
                        <td class="text-right">{{ number_format($b['total']['rats']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if(count($monthMatrix['characters']) > 1)
    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-table"></i> {{ trans('ratting-manager::insights.matrix_title') }}</h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-hover mb-0 rm-matrix">
                <thead>
                    <tr>
                        <th>{{ trans('ratting-manager::insights.month') }}</th>
                        @foreach($monthMatrix['characters'] as $c)
                            <th class="text-right">{{ $c['name'] }}</th>
                        @endforeach
                        <th class="text-right">{{ trans('ratting-manager::insights.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($monthMatrix['months'] as $m)
                        <tr>
                            <td>{{ $m['month'] }}</td>
                            @foreach($monthMatrix['characters'] as $c)
                                @if(($m['cells'][$c['id']] ?? 0) > 0)
                                    <td class="text-right">@rmisk($m['cells'][$c['id']])</td>
                                @else
                                    <td class="text-right rm-empty-cell">-</td>
                                @endif
                            @endforeach
                            <td class="text-right"><strong>{{ $m['total'] > 0 ? $fmt::isk($m['total']) : '-' }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
