{{-- Where the Outsiders alert looks. With nothing listed it watches nothing and sends nothing. --}}
<div class="rm-home-space mb-2">
    <label class="rm-mini-label">{{ trans('ratting-manager::notification_settings.home_space') }}</label>
    <div class="rm-notif-desc mb-2">{{ trans('ratting-manager::notification_settings.home_space_intro') }}</div>
    <div class="form-row">
        @foreach(['regions', 'constellations', 'systems'] as $kind)
            <div class="form-group col-md-4 mb-2">
                <label for="rm-home-{{ $kind }}" class="small mb-1">{{ trans('ratting-manager::notification_settings.home_' . $kind) }}</label>
                <input type="text" class="form-control form-control-sm" id="rm-home-{{ $kind }}" name="home_{{ $kind }}"
                       value="{{ old('home_' . $kind, $homeSpace[$kind]) }}"
                       placeholder="{{ trans('ratting-manager::notification_settings.home_' . $kind . '_placeholder') }}" autocomplete="off">
            </div>
        @endforeach
    </div>
    @if($homeSpace['set'])
        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> {{ trans('ratting-manager::notification_settings.home_space_watching', ['count' => number_format($homeSpace['systems_watched'])]) }}</small>
    @else
        <div class="rm-callout rm-callout-warn mb-0"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.home_space_missing_body') }}</div>
    @endif
</div>
