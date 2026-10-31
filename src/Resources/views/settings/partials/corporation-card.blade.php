{{--
    One corporation, with what SeAT knows about it. On the tracked list it
    offers Stop tracking; as a preview from the picker it offers Track, so the
    choice is confirmed with the details in front of the admin.
--}}
@php
    $c = $corporation;
    $preview = $preview ?? false;
@endphp
<div class="rm-corp-card {{ $preview ? 'rm-corp-card-preview' : '' }}" data-corporation-id="{{ $c['id'] }}">
    <div class="rm-corp-head">
        <img src="{{ $c['logo'] }}" alt="" class="rm-corp-logo" width="48" height="48" loading="lazy">
        <div class="rm-corp-title">
            <div class="rm-corp-name">
                <span class="rm-corp-ticker">[{{ $c['ticker'] }}]</span> {{ $c['name'] }}
                @if($c['tracked'])
                    <span class="badge badge-coverage-full ml-1"><i class="fas fa-check"></i> {{ trans('ratting-manager::settings.tracked') }}</span>
                @endif
            </div>
            <div class="rm-corp-sub">
                @if($c['alliance'])
                    <i class="fas fa-shield-alt"></i> {{ $c['alliance']['name'] }}@if($c['alliance']['ticker']) &lt;{{ $c['alliance']['ticker'] }}&gt;@endif
                @else
                    {{ trans('ratting-manager::settings.no_alliance') }}
                @endif
                @if($c['ceo'])
                    &middot; {{ trans('ratting-manager::settings.ceo', ['name' => $c['ceo']]) }}
                @endif
                @if($c['founded'])
                    &middot; {{ trans('ratting-manager::settings.founded', ['date' => $c['founded']]) }}
                @endif
            </div>
        </div>
        <div class="rm-corp-actions">
            @if($preview && !$c['tracked'])
                <form method="POST" action="{{ route('ratting-manager.settings.corporations.track') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="corporation_id" value="{{ $c['id'] }}">
                    <button type="submit" class="btn btn-sm btn-rm-primary"><i class="fas fa-check"></i> {{ trans('ratting-manager::settings.track_this') }}</button>
                </form>
                <button type="button" class="btn btn-sm btn-rm-secondary rm-corp-cancel">{{ trans('ratting-manager::settings.cancel') }}</button>
            @elseif(!$preview)
                <form method="POST" action="{{ route('ratting-manager.settings.corporations.untrack', $c['id']) }}" class="d-inline rm-confirm"
                      data-confirm="{{ trans('ratting-manager::settings.untrack_confirm', ['name' => $c['name']]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> {{ trans('ratting-manager::settings.untrack') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="rm-corp-stats">
        <div class="rm-corp-stat">
            <div class="rm-corp-stat-label">{{ trans('ratting-manager::settings.stat_tax') }}</div>
            <div class="rm-corp-stat-value">{{ $c['tax_rate'] === null ? '-' : $c['tax_rate'] . '%' }}</div>
        </div>
        <div class="rm-corp-stat">
            <div class="rm-corp-stat-label">{{ trans('ratting-manager::settings.stat_members') }}</div>
            <div class="rm-corp-stat-value">{{ number_format($c['members']) }}</div>
        </div>
        <div class="rm-corp-stat">
            <div class="rm-corp-stat-label">{{ trans('ratting-manager::settings.stat_on_seat') }}</div>
            <div class="rm-corp-stat-value">
                {{ number_format($c['on_seat']) }}
                @if($c['on_seat_share'] !== null)
                    <small>{{ $c['on_seat_share'] }}%</small>
                @endif
            </div>
            <div class="rm-corp-stat-sub">{{ trans('ratting-manager::settings.stat_players', ['count' => number_format($c['players'])]) }}</div>
        </div>
        <div class="rm-corp-stat">
            <div class="rm-corp-stat-label">{{ trans('ratting-manager::settings.stat_wallet') }}</div>
            <div class="rm-corp-stat-value">{{ number_format($c['wallet_tokens']) }}</div>
            <div class="rm-corp-stat-sub">{{ trans('ratting-manager::settings.stat_wallet_sub') }}</div>
        </div>
        <div class="rm-corp-stat {{ $c['last_bounty'] ? '' : 'rm-corp-stat-warn' }}">
            <div class="rm-corp-stat-label">{{ trans('ratting-manager::settings.stat_journal') }}</div>
            @if($c['last_bounty'])
                <div class="rm-corp-stat-value"><i class="fas fa-check text-success"></i> {{ number_format($c['bounty_rows']) }}</div>
                <div class="rm-corp-stat-sub">{{ trans('ratting-manager::settings.stat_journal_last', ['date' => substr($c['last_bounty'], 0, 10)]) }}</div>
            @else
                <div class="rm-corp-stat-value"><i class="fas fa-exclamation-triangle"></i> {{ trans('ratting-manager::settings.journal_missing') }}</div>
                <div class="rm-corp-stat-sub">{{ trans('ratting-manager::settings.stat_journal_none', ['count' => $c['corporation_wallet_tokens']]) }}</div>
            @endif
        </div>
    </div>

    @if($preview && !$c['tracked'])
        <p class="rm-corp-note">
            @if($c['last_bounty'])
                {{ trans('ratting-manager::settings.preview_ready') }}
            @else
                {{ trans('ratting-manager::settings.journal_missing_title') }}
            @endif
        </p>
    @elseif($preview)
        <p class="rm-corp-note">{{ trans('ratting-manager::settings.already_tracked') }}</p>
    @endif
</div>
