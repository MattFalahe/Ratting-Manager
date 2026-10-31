{{-- Players who ratted regularly and have stopped, by the Gone quiet settings. --}}
@php
    $limit = $limit ?? null;
    $shown = $limit === null ? $rows : array_slice($rows, 0, $limit);
    $settings = app(\RattingManager\Services\Configuration\Settings::class);
@endphp
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bed"></i> {{ trans('ratting-manager::dashboard.gone_quiet') }}</h3>
        <div class="card-tools">
            @include('ratting-manager::partials.view-all', ['count' => count($rows), 'limit' => $limit, 'url' => $moreUrl ?? null])
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::dashboard.gone_quiet_hint', ['regular' => $settings->get('data.regular_ratter_days'), 'quiet' => $settings->get('data.gone_quiet_days')]) }}</p>
        <table class="table table-sm table-hover mb-0" @if(!empty($tableId)) id="{{ $tableId }}" @endif>
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::dashboard.player') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.ticks') }}</th>
                    <th>{{ trans('ratting-manager::dashboard.last_tick') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shown as $g)
                    <tr>
                        <td>
                            <a href="{{ route('ratting-manager.ratters.show', $g['main_id']) }}">{{ $g['name'] }}</a>
                            @include('ratting-manager::partials.player-tags', ['row' => $g])
                        </td>
                        <td class="text-right">{{ $g['ticks'] }}</td>
                        <td data-order="{{ $g['last_tick'] }}"><span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $g['last_tick']) }}Z">{{ substr($g['last_tick'], 0, 16) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">{{ trans('ratting-manager::dashboard.nobody') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
