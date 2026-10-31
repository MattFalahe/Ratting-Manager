{{-- The import status, under the settings menu. --}}
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-download"></i> {{ trans('ratting-manager::settings.status') }}</h3>
    </div>
    <div class="card-body">
        <dl>
            <dt>{{ trans('ratting-manager::settings.last_import') }}</dt>
            <dd>
                @if($lastProcessAt)
                    <span class="eve-time" data-eve-time="{{ str_replace(' ', 'T', $lastProcessAt) }}Z">{{ $lastProcessAt }}</span> EVE
                @else
                    {{ trans('ratting-manager::settings.never') }}
                @endif
            </dd>
            <dt>{{ trans('ratting-manager::settings.initialized') }}</dt>
            <dd>{{ $initializedAt ? $initializedAt . ' EVE' : trans('ratting-manager::settings.not_initialized') }}</dd>
            @if(is_array($lastReport) && isset($lastReport['corporation_journal']))
                <dt>{{ trans('ratting-manager::settings.last_report') }}</dt>
                <dd>
                    @foreach(['corporation_journal', 'character_journal'] as $source)
                        @php $r = $lastReport[$source]['bounty']; @endphp
                        <small class="d-block text-muted">
                            {{ $source === 'corporation_journal' ? trans('ratting-manager::common.corporation') : trans('ratting-manager::ratter.characters') }}:
                            {{ trans('ratting-manager::settings.report_line', ['read' => $r['read'], 'created' => $r['created'], 'paired' => $r['paired']]) }}
                        </small>
                    @endforeach
                </dd>
            @endif
        </dl>
        <p class="text-muted small">{{ trans('ratting-manager::settings.initialize_intro') }}</p>
        <pre class="rm-command mb-3"><code>docker exec -it seat-docker-front-1 php artisan ratting-manager:initialize</code></pre>
        <a href="{{ route('ratting-manager.diagnostic') }}" class="btn btn-sm btn-rm-secondary"><i class="fas fa-stethoscope"></i> {{ trans('ratting-manager::settings.open_diagnostics') }}</a>
    </div>
</div>
