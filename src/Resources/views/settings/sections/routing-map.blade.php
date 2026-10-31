{{-- Where each notification goes now and who it mentions, worked out as WebhookDispatcher will when it sends. Read only. --}}
@php
    $liveRoutes = 0;
    $nowhere = 0;
    foreach ($routing as $entry) {
        $live = collect($entry['routes'])->where('live', true)->count();
        $liveRoutes += $live;
        if ($entry['type']->enabled && $live === 0) {
            $nowhere++;
        }
    }
@endphp
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-project-diagram"></i> {{ trans('ratting-manager::notification_settings.section_routing') }}</h3>
    </div>
    <div class="card-body p-0">
        <div class="px-3 pt-3">
            <p class="text-muted small mb-2">{{ trans('ratting-manager::notification_settings.routing_intro') }}</p>
            <div class="rm-chips">
                <span class="rm-chip"><strong>{{ $liveRoutes }}</strong> {{ trans('ratting-manager::notification_settings.routes_live') }}</span>
                @if($nowhere > 0)
                    <span class="rm-chip rm-chip-warn"><strong>{{ $nowhere }}</strong> {{ trans('ratting-manager::notification_settings.types_nowhere') }}</span>
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 28%;">{{ trans('ratting-manager::notification_settings.section_notifications') }}</th>
                        <th>{{ trans('ratting-manager::notification_settings.delivers_to') }}</th>
                        <th>{{ trans('ratting-manager::notification_settings.corporation') }}</th>
                        <th>{{ trans('ratting-manager::notification_settings.will_mention') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($routing as $key => $entry)
                        @php
                            $type = $entry['type'];
                            $routes = $entry['routes'];
                            $label = '<i class="fas ' . \RattingManager\Services\Notification\NotificationTypes::icon($key) . ' mr-1"></i>' . e(\RattingManager\Services\Notification\NotificationTypes::name($key));
                        @endphp
                        @if(!$type->enabled)
                            <tr class="text-muted">
                                <td>{!! $label !!}</td>
                                <td colspan="3"><i class="fas fa-power-off"></i> {{ trans('ratting-manager::notification_settings.switched_off') }}</td>
                            </tr>
                        @elseif($entry['blocked'])
                            <tr>
                                <td>{!! $label !!}</td>
                                <td colspan="3" class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ $entry['blocked'] }}</td>
                            </tr>
                        @elseif($routes === [])
                            <tr>
                                <td>{!! $label !!}</td>
                                <td colspan="3" class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.goes_nowhere') }}</td>
                            </tr>
                        @else
                            @foreach($routes as $i => $route)
                                <tr @unless($route['live']) class="text-muted" @endunless>
                                    @if($i === 0)
                                        <td rowspan="{{ count($routes) }}">{!! $label !!}</td>
                                    @endif
                                    <td>
                                        {{ $route['webhook']->description ?: '#' . $route['webhook']->id }}
                                        @unless($route['binding_enabled'])
                                            <span class="badge badge-secondary">{{ trans('ratting-manager::notification_settings.binding_off') }}</span>
                                        @endunless
                                        @unless($route['webhook']->enabled)
                                            <span class="badge badge-secondary">{{ trans('ratting-manager::notification_settings.webhook_off') }}</span>
                                        @endunless
                                    </td>
                                    <td>{{ $route['corporation'] ?? trans('ratting-manager::notification_settings.all_corporations') }}</td>
                                    <td>
                                        @include('ratting-manager::settings.partials.role-pill', ['desc' => $route['mention']])
                                        @if($route['tier'])
                                            <span class="rm-tier rm-tier-{{ $route['tier'] }}">{{ trans('ratting-manager::notification_settings.tier_' . $route['tier']) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
