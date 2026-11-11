@extends('web::layouts.grids.12')

@section('title', trans('ratting-manager::diagnostic.title'))
@section('page_header', trans('ratting-manager::diagnostic.title'))

@push('head')
<link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/ratting-manager.css') }}">
<style>
    /* Structure Manager's diagnostic chrome: underline tabs (.diag-tab), boxed
       sections (.diag-section) and key-value lists (.diag-kv). It lives in the
       view rather than the stylesheet, as it does in Structure Manager, since
       only this page uses it. */

    /* All rules below are scoped to .ratting-manager-wrapper.diagnostic-page
       so they cannot leak into other Ratting Manager views. */

    .ratting-manager-wrapper.diagnostic-page .diag-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #454d55;
        margin: 1.5rem 0 1.5rem 0;
        padding: 0;
        list-style: none;
        flex-wrap: wrap;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab {
        padding: 0.6rem 1.2rem;
        color: #8b95a5;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.15s;
        user-select: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab:hover {
        color: #c2c7d0;
        border-bottom-color: #3a4049;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab.active {
        color: var(--rm-info);
        border-bottom-color: var(--rm-info);
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab.danger.active {
        color: #e57373;
        border-bottom-color: #e57373;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab .diag-tab-count {
        font-size: 0.72rem;
        background: #454d55;
        color: #c2c7d0;
        padding: 1px 6px;
        border-radius: 8px;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab.active .diag-tab-count {
        background: rgba(23, 162, 184, 0.25);
        color: var(--rm-info);
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-pane {
        display: none;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-pane.active {
        display: block;
    }

    /* Loading spinner for lazy-loaded tabs (renders briefly during the
       redirect that populates the heavy section data). */
    .ratting-manager-wrapper.diagnostic-page .diag-loading {
        padding: 2rem;
        text-align: center;
        color: #94a3b8;
        font-size: 1rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-loading i {
        margin-right: 0.5rem;
        color: #6366f1;
    }

    /* Tab intro box: sits at the top of every tab pane and explains
       what the tab is for and when to use it. Diagnostic page is not
       in Help & Documentation, so these intros are the only place
       operators learn each tab's purpose. */
    .ratting-manager-wrapper.diagnostic-page .diag-tab-intro {
        padding: 0.85rem 1.1rem;
        background: rgba(99, 102, 241, 0.08);
        border-left: 3px solid #6366f1;
        border-radius: 5px;
        margin-bottom: 1.25rem;
        color: #c2c7d0;
        font-size: 0.92rem;
        line-height: 1.5;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-intro strong {
        color: #c7d2fe;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-intro p {
        margin-bottom: 0.4rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-intro p:last-child {
        margin-bottom: 0;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-tab-intro code {
        color: #a5b4fc;
        background: rgba(0, 0, 0, 0.25);
        padding: 0 0.25rem;
        border-radius: 3px;
    }

    .ratting-manager-wrapper.diagnostic-page .diag-section {
        background: #2a2f3a;
        border: 1px solid #454d55;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-section-header {
        padding: 0.8rem 1.2rem;
        background: #343a45;
        border-bottom: 1px solid #454d55;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-section-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        color: #fff;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-section-body {
        padding: 1.2rem;
        color: #c2c7d0;
    }

    /* SEMANTIC diagnostic status badges: DO NOT CHANGE colors */
    .ratting-manager-wrapper.diagnostic-page .diag-badge {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.25rem 0.55rem;
        border-radius: 0.25rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-badge.ok      { background: #1c6f3e; color: #d4f4e2; }
    .ratting-manager-wrapper.diagnostic-page .diag-badge.warn,
    .ratting-manager-wrapper.diagnostic-page .diag-badge.warning { background: #7a5a0f; color: #fff1c7; }
    .ratting-manager-wrapper.diagnostic-page .diag-badge.error,
    .ratting-manager-wrapper.diagnostic-page .diag-badge.danger  { background: #7a1d2b; color: #fbd5db; }
    .ratting-manager-wrapper.diagnostic-page .diag-badge.info    { background: #1d4d7a; color: #d0e4fb; }

    .ratting-manager-wrapper.diagnostic-page .diag-msg {
        margin: 0;
        padding: 0;
        color: #e2e8f0;
        font-size: 0.95rem;
    }

    .ratting-manager-wrapper.diagnostic-page .diag-detail-table {
        width: 100%;
        margin-top: 0.8rem;
        font-size: 0.85rem;
        border-collapse: collapse;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table th,
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table td {
        padding: 0.4rem 0.65rem;
        border-bottom: 1px solid #3a3f4a;
        color: #c2c7d0;
        text-align: left;
        vertical-align: top;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table th {
        color: #8b95a5;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.05em;
    }
    /* SEMANTIC row tints: DO NOT CHANGE */
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table tr.row-ok td   { }
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table tr.row-warn td { background: rgba(122, 90, 15, 0.08); }
    .ratting-manager-wrapper.diagnostic-page .diag-detail-table tr.row-error td { background: rgba(122, 29, 43, 0.10); }

    .ratting-manager-wrapper.diagnostic-page .diag-kv {
        display: grid;
        grid-template-columns: max-content 1fr;
        gap: 0.4rem 1rem;
        font-size: 0.9rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-kv dt { color: #8b95a5; font-weight: 500; }
    .ratting-manager-wrapper.diagnostic-page .diag-kv dd { margin: 0; color: #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .ratting-manager-wrapper.diagnostic-page .diag-summary {
        padding: 1rem 1.25rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid #454d55;
        background: #2a2f3a;
        color: #e2e8f0;
    }
    /* SEMANTIC summary stripes: DO NOT CHANGE */
    .ratting-manager-wrapper.diagnostic-page .diag-summary.ok    { border-left: 4px solid var(--rm-success); }
    .ratting-manager-wrapper.diagnostic-page .diag-summary.warn  { border-left: 4px solid var(--rm-warning); }
    .ratting-manager-wrapper.diagnostic-page .diag-summary.error { border-left: 4px solid var(--rm-danger); }

    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone {
        border: 2px solid #7a1d2b;
        background: rgba(122, 29, 43, 0.08);
        border-radius: 8px;
        padding: 1.25rem;
        margin-top: 2rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone h4 {
        color: #f5a8b0;
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone .alert {
        background: rgba(122, 29, 43, 0.15);
        border: 1px solid rgba(220, 53, 69, 0.4);
        color: #fbd5db;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone .dev-only-card {
        background: #2a2f3a;
        border: 1px solid #454d55;
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone .dev-only-card h5 {
        color: #fff;
        margin-top: 0;
        font-size: 1rem;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone label.confirm-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0.6rem 0;
        font-size: 0.88rem;
        color: #fbd5db;
    }
    .ratting-manager-wrapper.diagnostic-page .diag-danger-zone small {
        color: #8b95a5;
        font-size: 0.82rem;
    }

    .ratting-manager-wrapper.diagnostic-page code {
        background: rgba(0,0,0,0.4);
        color: #8be9fd;
        padding: 0.12em 0.3em;
        border-radius: 3px;
        font-size: 0.88em;
    }
</style>@endpush

@section('full')
<div class="ratting-manager-wrapper diagnostic-page">

    @php
        $overall = $counts['warn'] > 0 ? 'warn' : 'ok';
    @endphp
    <div class="diag-summary {{ $overall }}" style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
        <div style="flex:1; min-width:0;">
            <h4 style="margin:0 0 0.3rem 0;">{{ trans('ratting-manager::diagnostic.summary') }}</h4>
            <p style="margin:0;">
                <strong>{{ $overall === 'ok' ? trans('ratting-manager::diagnostic.all_ok') : trans('ratting-manager::diagnostic.some_warn') }}</strong>.
                OK: {{ $counts['ok'] }} &middot; {{ trans('ratting-manager::diagnostic.warnings') }}: {{ $counts['warn'] }} &middot; {{ trans('ratting-manager::diagnostic.informational') }}: {{ $counts['info'] }}
            </p>
            <p style="margin:0.3rem 0 0 0; font-size:0.8rem; color:#9aa3b3;">{{ trans('ratting-manager::diagnostic.cache_note') }}</p>
        </div>
        <div style="flex-shrink:0; display:flex; gap:0.4rem;">
            <a href="{{ route('ratting-manager.diagnostic', ['diag_tab' => $tab]) }}"
               class="btn btn-sm" style="background:#2a2f3a; color:#dfe3eb; border:1px solid #454d55; padding:0.4rem 0.8rem; border-radius:4px; text-decoration:none; white-space:nowrap;">
                <i class="fas fa-sync"></i> {{ trans('ratting-manager::diagnostic.reload') }}
            </a>
            <a href="{{ route('ratting-manager.diagnostic', ['diag_tab' => $tab, 'refresh' => 1]) }}"
               class="btn btn-sm" style="background:#667eea; color:#fff; border:none; padding:0.4rem 0.8rem; border-radius:4px; text-decoration:none; white-space:nowrap;">
                <i class="fas fa-bolt"></i> {{ trans('ratting-manager::diagnostic.force_refresh') }}
            </a>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-body">

    <ul class="diag-tabs" role="tablist">
        <li class="diag-tab" data-diag-target="health"><i class="fas fa-heartbeat"></i> {{ trans('ratting-manager::diagnostic.tab_health') }}</li>
        <li class="diag-tab" data-diag-target="master-test"><i class="fas fa-rocket"></i> {{ trans('ratting-manager::diagnostic.tab_master_test') }}</li>
        <li class="diag-tab" data-diag-target="settings-health"><i class="fas fa-sliders-h"></i> {{ trans('ratting-manager::diagnostic.tab_settings_health') }}</li>
        <li class="diag-tab" data-diag-target="data-integrity"><i class="fas fa-database"></i> {{ trans('ratting-manager::diagnostic.tab_data_integrity') }}</li>
        <li class="diag-tab" data-diag-target="tick-trace"><i class="fas fa-route"></i> {{ trans('ratting-manager::diagnostic.tab_tick_trace') }}</li>
        <li class="diag-tab" data-diag-target="notifications"><i class="fas fa-paper-plane"></i> {{ trans('ratting-manager::diagnostic.tab_notifications') }}</li>
    </ul>

    {{-- ============ HEALTH CHECKS ============ --}}
    <div class="diag-tab-pane" data-diag-pane="health">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.health_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.health_when') }}</p>
        </div>
        @foreach($health as $check)
            @include('ratting-manager::diagnostic.partials.check', ['check' => $check])
        @endforeach
    </div>

    {{-- ============ MASTER TEST ============ --}}
    <div class="diag-tab-pane" data-diag-pane="master-test" data-lazy="{{ $masterTest === null ? 'true' : 'false' }}">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.master_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.master_when') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.heads_up') }}</strong> {{ trans('ratting-manager::diagnostic.master_heads_up') }}</p>
        </div>
        @if($masterTest !== null)
            @foreach($masterTest as $group)
                <div class="diag-section">
                    <div class="diag-section-header">
                        <h3 class="diag-section-title">{{ $group['title'] }}</h3>
                    </div>
                    <div class="diag-section-body">
                        <p class="diag-msg" style="margin-bottom:0.8rem;">{{ $group['description'] }}</p>
                        <table class="diag-detail-table">
                            <tbody>
                                @foreach($group['checks'] as $check)
                                    <tr>
                                        <td style="width:28%;"><strong>{{ $check['label'] }}</strong></td>
                                        <td style="width:80px;"><span class="diag-badge {{ $check['status'] }}">{{ strtoupper($check['status']) }}</span></td>
                                        <td>
                                            {{ $check['message'] }}
                                            @if(!empty($check['details']) && $check['status'] !== 'ok')
                                                <dl class="diag-kv" style="margin-top:0.5rem;">
                                                    @foreach($check['details'] as $k => $v)
                                                        <dt>{{ $k }}</dt>
                                                        <dd>{{ $v }}</dd>
                                                    @endforeach
                                                </dl>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @else
            <div class="diag-loading">{{ trans('ratting-manager::diagnostic.loading') }}</div>
        @endif
    </div>

    {{-- ============ SETTINGS HEALTH ============ --}}
    <div class="diag-tab-pane" data-diag-pane="settings-health" data-lazy="{{ $settingsHealth === null ? 'true' : 'false' }}">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.settings_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.settings_when') }}</p>
        </div>
        @if($settingsHealth !== null)
            @foreach($settingsHealth['checks'] as $check)
                @include('ratting-manager::diagnostic.partials.check', ['check' => $check])
            @endforeach
            <div class="diag-section">
                <div class="diag-section-header"><h3 class="diag-section-title">{{ trans('ratting-manager::diagnostic.every_setting') }}</h3></div>
                <div class="diag-section-body">
                    <table class="diag-detail-table">
                        <thead><tr><th>Key</th><th>{{ trans('ratting-manager::diagnostic.scope') }}</th><th>{{ trans('ratting-manager::diagnostic.value') }}</th><th>{{ trans('ratting-manager::diagnostic.default') }}</th><th>{{ trans('ratting-manager::diagnostic.stored') }}</th></tr></thead>
                        <tbody>
                            @foreach($settingsHealth['rows'] as $row)
                                <tr>
                                    <td><code>{{ $row['key'] }}</code></td>
                                    <td>{{ $row['scope'] }}</td>
                                    <td><code>{{ \Illuminate\Support\Str::limit($row['value'], 80) }}</code></td>
                                    <td><code>{{ $row['default'] }}</code></td>
                                    <td>{{ $row['stored'] ? trans('ratting-manager::ratter.yes') : trans('ratting-manager::ratter.no') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="diag-loading">{{ trans('ratting-manager::diagnostic.loading') }}</div>
        @endif
    </div>

    {{-- ============ DATA INTEGRITY ============ --}}
    <div class="diag-tab-pane" data-diag-pane="data-integrity" data-lazy="{{ $dataIntegrity === null ? 'true' : 'false' }}">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.integrity_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.integrity_when') }}</p>
        </div>
        @if($dataIntegrity !== null)
            @foreach($dataIntegrity as $check)
                @include('ratting-manager::diagnostic.partials.check', ['check' => $check])
            @endforeach
        @else
            <div class="diag-loading">{{ trans('ratting-manager::diagnostic.loading') }}</div>
        @endif
    </div>

    {{-- ============ TICK TRACE ============ --}}
    <div class="diag-tab-pane" data-diag-pane="tick-trace">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.trace_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.trace_when') }}</p>
        </div>
        <div class="diag-section">
            <div class="diag-section-body">
                <form method="GET" action="{{ route('ratting-manager.diagnostic') }}" class="form-inline" style="gap:0.5rem;">
                    <input type="hidden" name="diag_tab" value="tick-trace">
                    <label for="rm-journal-id">{{ trans('ratting-manager::diagnostic.journal_id') }}</label>
                    <input type="number" min="1" name="journal_id" id="rm-journal-id" class="form-control form-control-sm" value="{{ $journalId }}" style="width:220px;">
                    <button type="submit" class="btn btn-sm btn-rm-primary"><i class="fas fa-route"></i> {{ trans('ratting-manager::diagnostic.trace') }}</button>
                </form>
            </div>
        </div>
        @if($traceResult !== null)
            @foreach($traceResult as $section)
                <div class="diag-section">
                    <div class="diag-section-header">
                        <h3 class="diag-section-title">{{ $section['title'] }}</h3>
                        <span class="diag-badge {{ $section['status'] }}">{{ strtoupper($section['status']) }}</span>
                    </div>
                    <div class="diag-section-body">
                        <dl class="diag-kv">
                            @foreach($section['rows'] as [$k, $v])
                                <dt>{{ $k }}</dt>
                                <dd>{{ $v }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endforeach
        @endif
    </div>


    {{-- ============ NOTIFICATION TESTING ============ --}}
    <div class="diag-tab-pane" data-diag-pane="notifications" data-lazy="{{ $notifications === null ? 'true' : 'false' }}">
        <div class="diag-tab-intro">
            <p><strong>{{ trans('ratting-manager::diagnostic.what_this_tab_does') }}</strong> {{ trans('ratting-manager::diagnostic.notif_what') }}</p>
            <p><strong>{{ trans('ratting-manager::diagnostic.when_to_use') }}</strong> {{ trans('ratting-manager::diagnostic.notif_when') }}</p>
        </div>
        @if(session('success'))
            <div class="alert alert-success"><i class="fas fa-check"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> {{ session('error') }}</div>
        @endif
        @if($notifications !== null)
            <div class="diag-section">
                <div class="diag-section-header">
                    <h3 class="diag-section-title"><i class="fas fa-paper-plane"></i> {{ trans('ratting-manager::diagnostic.notif_send_sample') }}</h3>
                </div>
                <div class="diag-section-body">
                    @if($notifications['webhooks']->isEmpty())
                        <p class="diag-msg">{{ trans('ratting-manager::diagnostic.notif_no_webhooks') }} <a href="{{ route('ratting-manager.settings.index') }}#webhooks">{{ trans('ratting-manager::diagnostic.notif_open_settings') }}</a></p>
                    @else
                        <p class="diag-msg" style="margin-bottom:0.6rem;">{{ trans('ratting-manager::diagnostic.notif_sample_note') }}</p>
                        <form method="POST" action="{{ route('ratting-manager.diagnostic.notification-test') }}" class="form-inline" style="gap:0.5rem;">
                            @csrf
                            <label for="rm-test-webhook" class="sr-only">{{ trans('ratting-manager::diagnostic.notif_webhook') }}</label>
                            <select name="webhook_id" id="rm-test-webhook" class="form-control form-control-sm" style="height:auto;">
                                @foreach($notifications['webhooks'] as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->description ?: '#' . $wh->id }}{{ $wh->enabled ? '' : ' (' . trans('ratting-manager::notification_settings.off') . ')' }}</option>
                                @endforeach
                            </select>
                            <label for="rm-test-type" class="sr-only">{{ trans('ratting-manager::diagnostic.notif_type') }}</label>
                            <select name="type" id="rm-test-type" class="form-control form-control-sm" style="height:auto;">
                                @foreach($notifications['types'] as $key)
                                    <option value="{{ $key }}">{{ \RattingManager\Services\Notification\NotificationTypes::name($key) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-rm-primary"><i class="fas fa-paper-plane"></i> {{ trans('ratting-manager::diagnostic.notif_send') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">
                    <h3 class="diag-section-title"><i class="fas fa-heartbeat"></i> {{ trans('ratting-manager::diagnostic.notif_health') }}</h3>
                </div>
                <div class="diag-section-body">
                    <table class="diag-detail-table">
                        <thead>
                            <tr>
                                <th>{{ trans('ratting-manager::diagnostic.notif_webhook') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_sent') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_avg') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_last_ok') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_last_error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications['webhooks'] as $wh)
                                @php $h = $notifications['health'][$wh->id] ?? null; @endphp
                                <tr class="{{ $h && $h['delivered'] < $h['attempts'] ? 'row-warn' : 'row-ok' }}">
                                    <td>{{ $wh->description ?: '#' . $wh->id }} <small style="color:#8b95a5;">{{ $wh->isSlack() ? 'Slack' : 'Discord' }}</small></td>
                                    <td>{{ $h ? $h['delivered'] . ' / ' . $h['attempts'] : '-' }}</td>
                                    <td>{{ $h && $h['avg_ms'] !== null ? $h['avg_ms'] . ' ms' : '-' }}</td>
                                    <td>{{ $h['last_success'] ?? '-' }}</td>
                                    <td>{{ $h && $h['last_error'] ? mb_substr((string) ($h['last_error']['message'] ?: 'HTTP ' . $h['last_error']['status']), 0, 140) : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ trans('ratting-manager::diagnostic.notif_no_webhooks') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">
                    <h3 class="diag-section-title"><i class="fas fa-history"></i> {{ trans('ratting-manager::diagnostic.notif_recent') }}</h3>
                </div>
                <div class="diag-section-body">
                    <table class="diag-detail-table">
                        <thead>
                            <tr>
                                <th>{{ trans('ratting-manager::diagnostic.notif_time') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_webhook') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_type') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_result') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_post') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications['recent'] as $d)
                                <tr class="{{ $d->success ? 'row-ok' : 'row-error' }}">
                                    <td style="white-space:nowrap;">{{ $d->attempted_at }}</td>
                                    <td>{{ $d->description ?? '-' }}</td>
                                    <td>{{ $d->type_key && \RattingManager\Services\Notification\NotificationTypes::has($d->type_key) ? \RattingManager\Services\Notification\NotificationTypes::name($d->type_key) : ($d->type_key === 'webhook_test' ? trans('ratting-manager::diagnostic.notif_webhook_test') : ($d->type_key ?? '-')) }}</td>
                                    <td>{{ $d->success ? 'HTTP ' . $d->status_code : ($d->status_code ? 'HTTP ' . $d->status_code : trans('ratting-manager::diagnostic.notif_no_answer')) }} &middot; {{ $d->duration_ms }} ms
                                        @if(!$d->success && $d->error_message)
                                            <br><small style="color:#e6a5ad;">{{ mb_substr($d->error_message, 0, 160) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $d->payload_summary }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ trans('ratting-manager::diagnostic.notif_nothing_sent') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">
                    <h3 class="diag-section-title"><i class="fas fa-lock"></i> {{ trans('ratting-manager::diagnostic.notif_open') }}</h3>
                </div>
                <div class="diag-section-body">
                    <p class="diag-msg" style="margin-bottom:0.6rem;">{{ trans('ratting-manager::diagnostic.notif_open_note') }}</p>
                    <table class="diag-detail-table">
                        <thead>
                            <tr>
                                <th>{{ trans('ratting-manager::diagnostic.notif_key') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_since') }}</th>
                                <th>{{ trans('ratting-manager::diagnostic.notif_last_sent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications['open'] as $o)
                                <tr>
                                    <td><code>{{ $o->alert_key }}</code></td>
                                    <td>{{ $o->first_seen_at }}</td>
                                    <td>{{ $o->last_sent_at }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">{{ trans('ratting-manager::diagnostic.notif_none_open') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="diag-loading">{{ trans('ratting-manager::diagnostic.loading') }}</div>
        @endif
    </div>
        </div>
    </div>
</div>
@endsection

@push('javascript')
<script>
(function ($) {
    'use strict';

    var $tabs = $('.diag-tab');
    var $panes = $('.diag-tab-pane');

    function setActive(target, syncUrl) {
        $tabs.removeClass('active').filter('[data-diag-target="' + target + '"]').addClass('active');
        $panes.removeClass('active').hide().filter('[data-diag-pane="' + target + '"]').addClass('active').show();

        // A heavy tab the server did not build for this request is fetched
        // by asking for the page again with that tab, so a plain visit to
        // Diagnostics stays quick.
        var pane = document.querySelector('[data-diag-pane="' + target + '"]');
        if (pane && pane.getAttribute('data-lazy') === 'true') {
            var url = new URL(window.location.href);
            url.searchParams.set('diag_tab', target);
            url.searchParams.delete('refresh');
            window.location.replace(url.toString());
            return;
        }

        if (syncUrl) {
            try {
                var current = new URL(window.location.href);
                current.searchParams.set('diag_tab', target);
                history.replaceState(null, '', current.toString());
            } catch (e) {}
        }
    }

    $tabs.on('click', function () {
        var target = $(this).data('diag-target');
        if (target) setActive(target, true);
    });

    setActive(@json($tab), false);
})(jQuery);
</script>
@endpush
