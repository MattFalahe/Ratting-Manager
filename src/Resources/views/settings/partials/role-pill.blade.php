{{-- A stored mention for reading: $desc from DiscordRoleResolver::describe(), null when nothing is set. --}}
@if(empty($desc))
    <span class="rm-role-none">{{ trans('ratting-manager::notification_settings.nobody') }}</span>
@elseif($desc['kind'] === 'none')
    <span class="rm-role-pill is-silenced"><i class="fas fa-bell-slash"></i> {{ trans('ratting-manager::notification_settings.no_ping') }}</span>
@elseif($desc['known'])
    <span class="rm-role-pill" title="{{ $desc['id'] }}">
        @if(!empty($desc['color']) && preg_match('/^#[0-9a-f]{6}$/i', $desc['color']))
            <span class="rm-role-dot" style="background: {{ $desc['color'] }};"></span>
        @endif
        {{ '@' . $desc['name'] }}
    </span>
@elseif($desc['kind'] === 'user')
    <span class="rm-role-pill is-user" title="{{ $desc['raw'] }}"><i class="fas fa-user"></i> {{ trans('ratting-manager::notification_settings.user_mention') }}</span>
@elseif($desc['kind'] === 'role')
    @if(!empty($roleProviders))
        <span class="rm-role-pill is-unknown" title="{{ $desc['raw'] }}"><i class="fas fa-question-circle"></i> {{ trans('ratting-manager::notification_settings.unknown_role', ['id' => $desc['id']]) }}</span>
    @else
        <code>{{ $desc['raw'] }}</code>
    @endif
@else
    <span class="rm-role-pill is-unknown" title="{{ $desc['raw'] }}"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.invalid_mention') }}</span>
@endif
