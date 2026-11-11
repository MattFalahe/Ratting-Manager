{{-- The NPCs killed most in the period, a fleet's shared payout counted once. $npcs are Insights npcs rows. --}}
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list-ol"></i> {{ trans('ratting-manager::insights.npcs_title') }}</h3>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ trans('ratting-manager::insights.npc') }}</th>
                    <th>{{ trans('ratting-manager::insights.faction') }}</th>
                    <th class="text-right">{{ trans('ratting-manager::insights.rats') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($npcs as $n)
                    <tr>
                        <td>
                            {{ $n['name'] }}
                            @if($n['rank'] !== 'regular')
                                <span class="badge badge-rank-{{ $n['rank'] }}">{{ trans('ratting-manager::common.rank_' . $n['rank']) }}</span>
                            @endif
                            <br><small class="text-muted">{{ trans('ratting-manager::common.size_' . $n['size']) }}</small>
                        </td>
                        <td><span class="badge badge-faction badge-faction-{{ $n['faction'] }}">{{ \RattingManager\Services\Npc\NpcRegistry::FACTIONS[$n['faction']] ?? ucfirst($n['faction']) }}</span></td>
                        <td class="text-right">{{ number_format($n['killed']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">{{ trans('ratting-manager::common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
