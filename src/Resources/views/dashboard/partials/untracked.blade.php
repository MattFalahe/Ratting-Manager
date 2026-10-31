{{-- Ratters the corporation journal pays who have no wallet token on SeAT. --}}
@php
    $limit = $limit ?? null;
    $shown = $limit === null ? $rows : array_slice($rows, 0, $limit);
@endphp
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-eye-slash"></i> {{ trans('ratting-manager::dashboard.untracked') }}</h3>
        <div class="card-tools">
            @include('ratting-manager::partials.view-all', ['count' => count($rows), 'limit' => $limit, 'url' => $moreUrl ?? null])
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <p class="text-muted small px-3 pt-2 mb-2">{{ trans('ratting-manager::dashboard.untracked_hint') }}</p>
        <table class="table table-sm table-hover mb-0" @if(!empty($tableId)) id="{{ $tableId }}" @endif>
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::common.character') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.ticks') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::dashboard.worked_out_income') }}</th>
                    <th>{{ trans('ratting-manager::dashboard.last_tick') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shown as $u)
                    <tr>
                        <td>
                            <a href="{{ route('ratting-manager.ratters.show', $u['character_id']) }}">{{ $u['name'] }}</a>
                            <br><small class="text-muted">{{ $u['corporation'] ?? trans('ratting-manager::dashboard.corporation_unknown') }} &middot; {{ $u['on_seat'] ? trans('ratting-manager::dashboard.on_seat') : trans('ratting-manager::dashboard.not_on_seat') }}</small>
                        </td>
                        <td class="text-right">{{ $u['ticks'] }}</td>
                        <td class="text-right" data-order="{{ $u['net'] }}"><span class="rm-estimated" title="{{ trans('ratting-manager::common.worked_out_title') }}">@rmisk($u['net'])</span></td>
                        <td data-order="{{ $u['last_tick'] }}"><span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $u['last_tick']) }}Z">{{ substr($u['last_tick'], 0, 16) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">{{ trans('ratting-manager::dashboard.nobody') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
