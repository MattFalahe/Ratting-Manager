{{-- Special spawns, newest first. On a dashboard $total says how many there are in all, for the View all link. --}}
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-star"></i> {{ trans('ratting-manager::dashboard.special_spawns') }}</h3>
        <div class="card-tools">
            @include('ratting-manager::partials.view-all', ['count' => $total ?? count($spawns), 'limit' => count($spawns), 'url' => $moreUrl ?? null])
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm mb-0" @if(!empty($tableId)) id="{{ $tableId }}" @endif>
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::dashboard.when') }}</th>
                    <th>{{ trans('ratting-manager::dashboard.npc') }}</th>
                    @if($showCharacter)
                        <th>{{ trans('ratting-manager::common.character') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($spawns as $spawn)
                    <tr>
                        <td data-order="{{ $spawn['when'] }}">
                            <span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $spawn['when']) }}Z">{{ substr($spawn['when'], 0, 16) }}</span><br>
                            <small class="text-muted">{{ $spawn['system'] }}</small>
                        </td>
                        <td>
                            {{ $spawn['npc'] }}
                            @if($spawn['quantity'] > 1)
                                x{{ $spawn['quantity'] }}
                            @endif
                            <br>
                            <span class="badge badge-faction badge-faction-{{ $spawn['faction'] }}">{{ \RattingManager\Services\Npc\NpcRegistry::FACTIONS[$spawn['faction']] ?? $spawn['faction'] }}</span>
                            @if($spawn['rank'] !== 'regular')
                                <span class="badge badge-rank-{{ $spawn['rank'] }}">{{ trans('ratting-manager::common.rank_' . $spawn['rank']) }}</span>
                            @endif
                            @if($spawn['size'] === 'capital')
                                <span class="badge badge-size">{{ trans('ratting-manager::common.size_capital') }}</span>
                            @endif
                        </td>
                        @if($showCharacter)
                            <td>{{ $spawn['character'] }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $showCharacter ? 3 : 2 }}" class="text-center text-muted">{{ trans('ratting-manager::dashboard.nobody') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
