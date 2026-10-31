{{-- Webhooks: where posts go. What each one carries is picked under Notifications. --}}
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-satellite-dish"></i> {{ trans('ratting-manager::notification_settings.section_webhooks') }}</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-sm btn-rm-primary rm-webhook-add"><i class="fas fa-plus"></i> {{ trans('ratting-manager::notification_settings.add_webhook') }}</button>
        </div>
    </div>
    <div class="card-body p-0">
        <p class="text-muted small px-3 pt-3 mb-2">{{ trans('ratting-manager::notification_settings.webhooks_intro') }}</p>
        @if($webhooks->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x mb-2"></i>
                <p class="mb-2">{{ trans('ratting-manager::notification_settings.no_webhooks') }}</p>
                <button type="button" class="btn btn-sm btn-rm-primary rm-webhook-add"><i class="fas fa-plus"></i> {{ trans('ratting-manager::notification_settings.add_webhook') }}</button>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ trans('ratting-manager::notification_settings.name') }}</th>
                            <th>{{ trans('ratting-manager::notification_settings.platform') }}</th>
                            <th>{{ trans('ratting-manager::notification_settings.bound_types') }}</th>
                            <th>{{ trans('ratting-manager::notification_settings.health') }}</th>
                            <th class="text-right">{{ trans('ratting-manager::notification_settings.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($webhooks as $wh)
                            @php
                                $health = $webhookHealth[$wh->id] ?? null;
                                $sends = $boundTypes[$wh->id] ?? [];
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $wh->description ?: '#' . $wh->id }}</strong>
                                    @unless($wh->enabled)
                                        <span class="badge badge-secondary ml-1">{{ trans('ratting-manager::notification_settings.off') }}</span>
                                    @endunless
                                    <br>
                                    @if($wh->corporation_id)
                                        <small class="text-info"><i class="fas fa-building"></i> {{ $webhookCorporations[$wh->corporation_id] ?? 'Corporation #' . $wh->corporation_id }}</small>
                                    @else
                                        <small class="text-muted"><i class="fas fa-globe"></i> {{ trans('ratting-manager::notification_settings.all_corporations') }}</small>
                                    @endif
                                    <br>
                                    @if($wh->urlLost())
                                        <span class="text-warning" title="{{ trans('ratting-manager::notification_settings.url_lost_title') }}"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::notification_settings.url_lost') }}</span>
                                    @else
                                        <span class="rm-url">{{ $wh->maskedUrl() }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($wh->isSlack())
                                        <span class="badge badge-info"><i class="fab fa-slack"></i> Slack</span>
                                    @else
                                        <span class="badge badge-primary"><i class="fab fa-discord"></i> Discord</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($sends as $key)
                                        <span class="rm-type-badge">{{ \RattingManager\Services\Notification\NotificationTypes::name($key) }}</span>
                                    @empty
                                        <small class="text-muted">{{ trans('ratting-manager::notification_settings.nothing_bound') }}</small>
                                    @endforelse
                                </td>
                                <td>
                                    @if($health)
                                        <span class="{{ $health['delivered'] === $health['attempts'] ? 'text-success' : 'text-warning' }}">
                                            {{ trans('ratting-manager::notification_settings.health_value', ['delivered' => $health['delivered'], 'attempts' => $health['attempts']]) }}
                                        </span>
                                        @if($health['last_error'])
                                            <br><small class="text-muted" title="{{ $health['last_error']['message'] }}">{{ trans('ratting-manager::notification_settings.last_error') }}: HTTP {{ $health['last_error']['status'] }}</small>
                                        @endif
                                    @else
                                        <small class="text-muted">{{ trans('ratting-manager::notification_settings.health_none') }}</small>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap">
                                    <form method="POST" action="{{ route('ratting-manager.settings.webhooks.test', $wh->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-rm-secondary" title="{{ trans('ratting-manager::notification_settings.test') }}"><i class="fas fa-paper-plane"></i></button>
                                    </form>
                                    <button type="button" class="btn btn-xs btn-rm-secondary rm-webhook-edit" title="{{ trans('ratting-manager::notification_settings.edit') }}"
                                            data-url="{{ route('ratting-manager.settings.webhooks.update', $wh->id) }}"
                                            data-description="{{ $wh->description }}"
                                            data-masked-url="{{ $wh->maskedUrl() }}"
                                            data-url-lost="{{ $wh->urlLost() ? 1 : 0 }}"
                                            data-types="{{ json_encode(array_values($sends)) }}"
                                            data-corporation="{{ $wh->corporation_id }}"
                                            data-enabled="{{ $wh->enabled ? 1 : 0 }}"><i class="fas fa-edit"></i></button>
                                    <form method="POST" action="{{ route('ratting-manager.settings.webhooks.destroy', $wh->id) }}" class="d-inline rm-confirm" data-confirm="{{ trans('ratting-manager::notification_settings.delete_confirm') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-danger" title="{{ trans('ratting-manager::notification_settings.delete') }}"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
