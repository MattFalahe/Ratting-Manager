{{--
    Corporations to track: the ones tracked now, and a picker to search
    SeAT's corporations, see one's details and confirm tracking it. Then the
    sessions, lists and watch list, in their own form.
--}}
@php
    $pickList = array_values(array_filter($corporationList, fn ($c) => !$c['tracked']));
@endphp
<div class="card card-dark rm-card-above">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-building"></i> {{ trans('ratting-manager::settings.corporations') }}</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ trans('ratting-manager::settings.corporations_intro') }}</p>

        @forelse($trackedCorporations as $corporation)
            @include('ratting-manager::settings.partials.corporation-card', ['corporation' => $corporation, 'preview' => false])
        @empty
            <div class="rm-callout rm-callout-warn"><i class="fas fa-info-circle"></i> {{ trans('ratting-manager::settings.none_tracked') }}</div>
        @endforelse

        <div class="rm-corp-picker mt-3">
            <label for="rm-corp-search" class="rm-mini-label">{{ trans('ratting-manager::settings.add_corporation') }}</label>
            @if($pickList === [])
                <p class="text-muted mb-0">{{ trans($corporationList === [] ? 'ratting-manager::settings.no_corporations' : 'ratting-manager::settings.all_tracked') }}</p>
            @else
                <div class="rm-typeahead">
                    <input type="text" id="rm-corp-search" class="form-control" autocomplete="off" spellcheck="false"
                           placeholder="{{ trans('ratting-manager::settings.search_placeholder') }}"
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="rm-corp-options">
                    <button type="button" class="rm-typeahead-toggle" tabindex="-1" aria-label="{{ trans('ratting-manager::settings.show_all') }}"><i class="fas fa-chevron-down"></i></button>
                    <ul class="rm-typeahead-list" id="rm-corp-options" role="listbox" hidden></ul>
                </div>
                <small class="form-text text-muted">{{ trans('ratting-manager::settings.search_help') }}</small>
                <div id="rm-corp-preview" class="mt-3" aria-live="polite"></div>
                <script type="application/json" id="rm-corp-data">@json($pickList)</script>
                <div id="rm-corp-text" hidden
                     data-url="{{ route('ratting-manager.settings.corporations.show', 0) }}"
                     data-on-seat="{{ trans('ratting-manager::settings.option_on_seat') }}"
                     data-none="{{ trans('ratting-manager::settings.no_match') }}"
                     data-loading="{{ trans('ratting-manager::settings.loading') }}"
                     data-failed="{{ trans('ratting-manager::settings.load_failed') }}"></div>
            @endif
        </div>
    </div>
</div>

<form method="POST" action="{{ route('ratting-manager.settings.update') }}">
    @csrf

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sliders-h"></i> {{ trans('ratting-manager::settings.data') }}</h3>
    </div>
    <div class="card-body">
        {{-- The line stays after the setting goes back to 0, so this one follows the line, not the setting. --}}
        @if($rawKeptFrom)
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::settings.raw_ticks_dropped', ['date' => $rawKeptFrom->toDateString()]) }}</div>
        @elseif((int) $values['raw_tick_days'] > 0)
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::settings.raw_ticks_on', ['days' => $values['raw_tick_days']]) }}</div>
        @endif
        <div class="row">
            @foreach([
                ['session_gap_minutes', 'session_gap', 'session_gap_help', 20, 240],
                ['gone_quiet_days', 'gone_quiet_days', 'gone_quiet_help', 1, 365],
                ['regular_ratter_days', 'regular_days', 'regular_help', 7, \RattingManager\Services\Ratting\RawTicks::KEEP_DAYS],
                ['leaderboard_size', 'leaderboard_size', 'leaderboard_size_help', 5, 500],
                ['raw_tick_days', 'raw_tick_days', 'raw_tick_days_help', 0, \RattingManager\Services\Ratting\RawTicks::KEEP_DAYS],
            ] as [$field, $label, $help, $min, $max])
                <div class="col-md-6 form-group">
                    <label for="rm-{{ $field }}">{{ trans('ratting-manager::settings.' . $label) }}</label>
                    <input type="number" class="form-control" id="rm-{{ $field }}" name="{{ $field }}" min="{{ $min }}" max="{{ $max }}" value="{{ old($field, $values[$field]) }}" required>
                    <small class="form-text text-muted">{{ trans('ratting-manager::settings.' . $help) }}</small>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-lock"></i> {{ trans('ratting-manager::settings.access') }}</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ trans('ratting-manager::settings.access_intro') }}</p>
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="rm-members-only" name="members_only" value="1" @if(old('members_only', $values['members_only'])) checked @endif>
            <label class="custom-control-label" for="rm-members-only">{{ trans('ratting-manager::settings.members_only') }}</label>
        </div>
        <small class="form-text text-muted">{{ trans('ratting-manager::settings.members_only_help') }}</small>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-star"></i> {{ trans('ratting-manager::settings.spawns') }}</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ trans('ratting-manager::settings.spawns_intro') }}</p>
        <div class="form-group">
            <label for="rm-watch">{{ trans('ratting-manager::settings.watch_type_ids') }}</label>
            <input type="text" class="form-control" id="rm-watch" name="watch_type_ids" value="{{ old('watch_type_ids', $values['watch_type_ids']) }}">
            <small class="form-text text-muted">{{ trans('ratting-manager::settings.watch_type_ids_help') }}</small>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-rm-primary"><i class="fas fa-save"></i> {{ trans('ratting-manager::settings.save') }}</button>
</form>
