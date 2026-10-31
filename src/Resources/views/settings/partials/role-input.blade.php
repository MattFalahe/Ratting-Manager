{{-- A role mention field: typed, or picked by name from the shared role list, with a No ping button. --}}
<div class="rm-role-field">
    <div class="rm-role-input-group">
        <input type="text" class="form-control form-control-sm rm-role-input" name="{{ $name }}" value="{{ $value }}"
               placeholder="{{ $placeholder }}" @if(!empty($roles)) list="rm-role-list" @endif autocomplete="off">
        <button type="button" class="btn btn-sm btn-rm-secondary rm-no-ping" title="{{ trans('ratting-manager::notification_settings.no_ping') }}">
            <i class="fas fa-bell-slash"></i>
        </button>
    </div>
    <div class="rm-role-name"></div>
</div>
