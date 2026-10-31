<div class="card card-dark">
    <div class="card-body text-center py-5">
        <i class="fas fa-crosshairs fa-3x mb-3 text-muted"></i>
        <h4>{{ trans('ratting-manager::common.not_configured_title') }}</h4>
        @if($isAdmin ?? false)
            <p class="text-muted">{{ trans('ratting-manager::common.not_configured_admin') }}</p>
            <a href="{{ route('ratting-manager.settings.index') }}" class="btn btn-rm-primary">
                <i class="fas fa-cog"></i> {{ trans('ratting-manager::common.open_settings') }}
            </a>
        @else
            <p class="text-muted">{{ trans('ratting-manager::common.not_configured_member') }}</p>
        @endif
    </div>
</div>
