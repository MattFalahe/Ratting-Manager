{{--
    What each notification type sends to and who it mentions, and the alert
    thresholds, in one form. A webhook is only sent a type an admin ticked
    for it here or when adding it.
--}}
@php
    $types = \RattingManager\Services\Notification\NotificationTypes::class;
    $roleJs = [];
    foreach ($roleLookup as $id => $role) {
        $roleJs[$id] = ['name' => $role['name'], 'color' => $role['color']];
    }
    $roleData = [
        'available' => !empty($roleProviders),
        'roles' => (object) $roleJs,
        'text' => [
            'no_ping' => trans('ratting-manager::notification_settings.no_ping'),
            'user' => trans('ratting-manager::notification_settings.user_mention'),
            'unknown_role' => trans('ratting-manager::notification_settings.unknown_role', ['id' => ':id']),
            'invalid' => trans('ratting-manager::notification_settings.invalid_mention'),
        ],
    ];
@endphp
<script type="application/json" id="rm-role-data">@json($roleData)</script>
@if(!empty($roles))
    <datalist id="rm-role-list">
        @foreach($roles as $role)
            <option value="{{ $role['mention_format'] }}">{{ $role['name'] }}</option>
        @endforeach
    </datalist>
@endif

<form method="POST" action="{{ route('ratting-manager.settings.notifications') }}">
    @csrf

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bell"></i> {{ trans('ratting-manager::notification_settings.section_notifications') }}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">{{ trans('ratting-manager::notification_settings.notifications_intro') }}</p>
            <div class="rm-callout">
                <i class="fas fa-at"></i> <strong>{{ trans('ratting-manager::notification_settings.mention_order') }}.</strong>
                {!! trans('ratting-manager::notification_settings.mention_order_body', ['none' => '<i class="fas fa-bell-slash"></i> <strong>' . e(trans('ratting-manager::notification_settings.no_ping')) . '</strong>']) !!}
            </div>
            @if(!empty($roleProviders))
                <div class="rm-callout rm-callout-ok mb-0"><i class="fas fa-check-circle"></i> {{ trans('ratting-manager::notification_settings.role_sources', ['sources' => implode(', ', $roleProviders)]) }}</div>
            @else
                <div class="rm-callout rm-callout-warn mb-0"><i class="fas fa-info-circle"></i> {{ trans('ratting-manager::notification_settings.role_sources_none') }}</div>
            @endif
        </div>
    </div>

    @foreach([\RattingManager\Services\Notification\NotificationTypes::ALERTS => $alertTypes, \RattingManager\Services\Notification\NotificationTypes::REPORTS => $reportTypes] as $group => $keys)
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas {{ $group === 'alerts' ? 'fa-exclamation-circle' : 'fa-file-alt' }}"></i>
                    {{ trans('ratting-manager::notifications.group_' . $group) }}
                </h3>
            </div>
            <div class="card-body">
                @foreach($keys as $key)
                    @php
                        $type = $routing[$key]['type'];
                        $blocked = $routing[$key]['blocked'];
                        $bound = $type->webhooks->keyBy('id');
                        $live = collect($routing[$key]['routes'])->where('live', true)->count();
                    @endphp
                    <div class="rm-notif-type" data-enabled="{{ $type->enabled ? '1' : '0' }}">
                        <div class="rm-notif-title-row">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input rm-type-toggle" id="rm-type-{{ $key }}" name="types[{{ $key }}][enabled]" value="1" @if($type->enabled) checked @endif>
                                <label class="custom-control-label" for="rm-type-{{ $key }}"><span class="sr-only">{{ $types::name($key) }}</span></label>
                            </div>
                            <i class="fas {{ $types::icon($key) }}"></i>
                            <span class="rm-notif-title">{{ $types::name($key) }}</span>
                            @if($key === 'outsiders' && !$homeSpace['set'])
                                <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.home_space_missing') }}</span>
                            @endif
                        </div>
                        <div class="rm-notif-body">
                            <div class="rm-notif-desc">{{ $types::description($key) }}</div>
                            <div class="form-group mb-2">
                                <label class="rm-mini-label">{{ trans('ratting-manager::notification_settings.default_role') }}</label>
                                @include('ratting-manager::settings.partials.role-input', [
                                    'name' => "types[{$key}][role]",
                                    'value' => old("types.{$key}.role", $type->role_mention),
                                    'placeholder' => trans('ratting-manager::notification_settings.default_role_placeholder'),
                                ])
                            </div>
                            @if($key === 'outsiders')
                                @include('ratting-manager::settings.partials.home-space')
                            @endif
                            @if($webhooks->isEmpty())
                                <p class="text-muted small mb-0"><a href="#webhooks" class="rm-section-link" data-section="webhooks">{{ trans('ratting-manager::notification_settings.add_webhooks_first') }}</a></p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm rm-bindings-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">{{ trans('ratting-manager::notification_settings.send_here') }}</th>
                                                <th>{{ trans('ratting-manager::notification_settings.webhook') }}</th>
                                                <th>{{ trans('ratting-manager::notification_settings.own_role') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($webhooks as $wh)
                                                @php $binding = $bound[$wh->id] ?? null; @endphp
                                                <tr>
                                                    <td>
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="rm-bind-{{ $key }}-{{ $wh->id }}" name="bind[{{ $key }}][{{ $wh->id }}][on]" value="1" @if($binding && $binding->pivot->enabled) checked @endif>
                                                            <label class="custom-control-label" for="rm-bind-{{ $key }}-{{ $wh->id }}"><span class="sr-only">{{ $wh->description }}</span></label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        {{ $wh->description ?: '#' . $wh->id }}
                                                        <small class="text-muted">{{ $wh->corporation_id ? ($webhookCorporations[$wh->corporation_id] ?? '#' . $wh->corporation_id) : trans('ratting-manager::notification_settings.all_corporations') }}</small>
                                                        @unless($wh->enabled)
                                                            <span class="badge badge-secondary">{{ trans('ratting-manager::notification_settings.off') }}</span>
                                                        @endunless
                                                    </td>
                                                    <td>
                                                        @include('ratting-manager::settings.partials.role-input', [
                                                            'name' => "bind[{$key}][{$wh->id}][role]",
                                                            'value' => old("bind.{$key}.{$wh->id}.role", $binding ? $binding->pivot->role_mention : null),
                                                            'placeholder' => trans('ratting-manager::notification_settings.own_role_placeholder'),
                                                        ])
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                {{-- A blocked type says why in its own card, so "goes nowhere" would only mislead. --}}
                                @if($type->enabled && $live === 0 && !$blocked)
                                    <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.goes_nowhere') }}</small>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-sliders-h"></i> {{ trans('ratting-manager::notification_settings.thresholds') }}</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="rm-spawn-rank">{{ trans('ratting-manager::notification_settings.spawn_min_rank') }}</label>
                    <select class="form-control" id="rm-spawn-rank" name="spawn_min_rank">
                        @foreach(\RattingManager\Services\Npc\NpcRegistry::SPECIAL_RANKS as $rank)
                            <option value="{{ $rank }}" @if(old('spawn_min_rank', $notificationValues['spawn_min_rank']) === $rank) selected @endif>{{ trans('ratting-manager::common.rank_' . $rank) }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">{{ trans('ratting-manager::notification_settings.spawn_min_rank_help') }}</small>
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="rm-spawn-capitals" name="spawn_capitals" value="1" @if($notificationValues['spawn_capitals']) checked @endif>
                        <label class="custom-control-label" for="rm-spawn-capitals">{{ trans('ratting-manager::notification_settings.spawn_capitals') }}</label>
                    </div>
                </div>
                @foreach([
                    ['spawn_max_age_hours', 'spawn_max_age', 1, 168],
                    ['stalled_hours', 'stalled_hours', 2, 168],
                    ['outsider_min_kills', 'outsider_min_kills', 10, 10000],
                    ['ship_lost_minutes', 'ship_lost_minutes', 10, 240],
                    ['rankings_size', 'rankings_size', 3, 25],
                ] as [$field, $label, $min, $max])
                    <div class="col-md-6 form-group">
                        <label for="rm-{{ $field }}">{{ trans('ratting-manager::notification_settings.' . $label) }}</label>
                        <input type="number" class="form-control" id="rm-{{ $field }}" name="{{ $field }}" min="{{ $min }}" max="{{ $max }}" value="{{ old($field, $notificationValues[$field]) }}" required>
                        <small class="form-text text-muted">{{ trans('ratting-manager::notification_settings.' . $label . '_help') }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-rm-primary mb-3"><i class="fas fa-save"></i> {{ trans('ratting-manager::notification_settings.save_notifications') }}</button>
</form>
