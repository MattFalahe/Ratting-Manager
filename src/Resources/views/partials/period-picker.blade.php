{{-- Period, and optionally corporation or character, as a GET form so the choice survives a reload and can be shared as a link. --}}
<form method="GET" action="{{ $action ?? url()->current() }}" class="rm-filters" @if(!empty($formId)) id="{{ $formId }}" @endif>
    @if(!empty($corporations) && count($corporations) > 1)
        <select name="corporation" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::common.corporation') }}">
            <option value="">{{ trans('ratting-manager::common.all_corporations') }}</option>
            @foreach($corporations as $id => $name)
                <option value="{{ $id }}" @if(($pickedCorporation ?? null) === $id) selected @endif>{{ $name }}</option>
            @endforeach
        </select>
    @endif
    @if(!empty($characterOptions) && count($characterOptions) > 1)
        <select name="character" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::common.character') }}">
            <option value="">{{ trans('ratting-manager::common.all_characters') }}</option>
            @foreach($characterOptions as $id => $name)
                <option value="{{ $id }}" @if(($pickedCharacter ?? null) === $id) selected @endif>{{ $name }}</option>
            @endforeach
        </select>
    @endif
    @if($showPeriod ?? true)
        @php
            $periods = \RattingManager\Services\Ratting\Period::class;
            $isRange = $period->isRange();
        @endphp
        <select name="period" class="form-control form-control-sm rm-period-select" aria-label="{{ trans('ratting-manager::common.period') }}">
            @foreach($periods::CHOICES as $choice)
                <option value="{{ $choice }}" @if($period->key === $choice) selected @endif>{{ trans('ratting-manager::common.period_' . $choice) }}</option>
            @endforeach
            <optgroup label="{{ trans('ratting-manager::common.period_months') }}">
                @foreach($periods::monthChoices() as $choice => $label)
                    <option value="{{ $choice }}" @if($period->key === $choice) selected @endif>{{ $label }}</option>
                @endforeach
            </optgroup>
            <option value="custom" @if($isRange) selected @endif>{{ trans('ratting-manager::common.period_custom') }}</option>
        </select>
        {{-- Sent only while Custom range is picked (partials/head); otherwise hidden and switched off. --}}
        <span class="rm-period-range" @unless($isRange) hidden @endunless>
            <input type="date" name="from" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::common.period_from') }}"
                   min="{{ $periods::earliest()->toDateString() }}" max="{{ now('UTC')->toDateString() }}"
                   value="{{ $period->start->toDateString() }}" @unless($isRange) disabled @endunless>
            <span>{{ trans('ratting-manager::common.period_to') }}</span>
            <input type="date" name="to" class="form-control form-control-sm" aria-label="{{ trans('ratting-manager::common.period_to') }}"
                   min="{{ $periods::earliest()->toDateString() }}" max="{{ now('UTC')->toDateString() }}"
                   value="{{ $period->end->toDateString() }}" @unless($isRange) disabled @endunless>
            <button type="submit" class="btn btn-sm btn-rm-primary">{{ trans('ratting-manager::common.apply') }}</button>
        </span>
    @else
        <input type="hidden" name="period" value="{{ $period->key }}">
    @endif
    {{-- Every other pick applies as it is made (partials/head). --}}
    <noscript><button type="submit" class="btn btn-sm btn-rm-primary">{{ trans('ratting-manager::common.apply') }}</button></noscript>
</form>
