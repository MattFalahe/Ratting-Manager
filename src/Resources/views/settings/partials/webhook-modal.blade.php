{{-- Add or edit a webhook and what it is sent. Moved to <body> by the page script; filled for editing from the row's button. --}}
<div class="modal fade rm-modal" id="rm-webhook-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('ratting-manager.settings.webhooks.store') }}" data-store-url="{{ route('ratting-manager.settings.webhooks.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="rm-modal-title-add"><i class="fas fa-plus"></i> {{ trans('ratting-manager::notification_settings.add_webhook') }}</span>
                        <span class="rm-modal-title-edit" style="display: none;"><i class="fas fa-edit"></i> {{ trans('ratting-manager::notification_settings.edit_webhook') }}</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ trans('ratting-manager::notification_settings.cancel') }}"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rm-wh-name">{{ trans('ratting-manager::notification_settings.name') }}</label>
                        <input type="text" class="form-control" id="rm-wh-name" name="description" maxlength="255" required placeholder="{{ trans('ratting-manager::notification_settings.name_placeholder') }}">
                    </div>
                    <div class="form-group">
                        <label for="rm-wh-url">{{ trans('ratting-manager::notification_settings.url') }}</label>
                        <input type="url" class="form-control" id="rm-wh-url" name="webhook_url" maxlength="500" placeholder="https://discord.com/api/webhooks/..." data-placeholder="https://discord.com/api/webhooks/..." autocomplete="off" required>
                        <small class="form-text rm-wh-url-help">{{ trans('ratting-manager::notification_settings.url_help') }}</small>
                        <small class="form-text rm-wh-url-keep" style="display: none;">{{ trans('ratting-manager::notification_settings.url_keep') }}</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="rm-wh-corp">{{ trans('ratting-manager::notification_settings.corporation') }}</label>
                            <select class="form-control" id="rm-wh-corp" name="corporation_id">
                                <option value="">{{ trans('ratting-manager::notification_settings.all_corporations') }}</option>
                                @foreach($webhookCorporations as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="rm-wh-enabled" name="enabled" value="1" checked>
                                <label class="custom-control-label" for="rm-wh-enabled">{{ trans('ratting-manager::notification_settings.enabled') }}</label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>{{ trans('ratting-manager::notification_settings.send_these') }}</label>
                        <div class="row">
                            @foreach(\RattingManager\Services\Notification\NotificationTypes::keys() as $key)
                                @php $suggested = in_array($key, ['special_spawn', 'data_stalled'], true); @endphp
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input rm-wh-type" id="rm-wh-type-{{ $key }}" name="types[]" value="{{ $key }}" data-suggested="{{ $suggested ? 1 : 0 }}" @if($suggested) checked @endif>
                                        <label class="custom-control-label" for="rm-wh-type-{{ $key }}">{{ \RattingManager\Services\Notification\NotificationTypes::name($key) }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <small class="form-text">{{ trans('ratting-manager::notification_settings.send_these_help') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('ratting-manager::notification_settings.cancel') }}</button>
                    <button type="submit" class="btn btn-rm-primary"><i class="fas fa-save"></i> {{ trans('ratting-manager::notification_settings.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
