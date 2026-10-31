@extends('web::layouts.grids.12')

@section('title', trans('ratting-manager::help.title'))
@section('page_header', trans('ratting-manager::help.title'))

@push('head')
<link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/ratting-manager.css') }}">
<style>
    .help-wrapper {
        display: flex;
        gap: 20px;
    }

    .help-sidebar {
        flex: 0 0 280px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }

    .help-content {
        flex: 1;
        min-width: 0;
        color: #d1d5db !important;
    }

    .help-nav .nav-link {
        color: #e2e8f0 !important;
        border-radius: 5px;
        margin-bottom: 5px;
        padding: 10px 15px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }

    .help-nav .nav-link:hover {
        background: rgba(102, 126, 234, 0.2);
    }

    .help-nav .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .help-nav .nav-link i {
        width: 24px;
        text-align: center;
        margin-right: 10px;
    }

    .help-section {
        display: none;
        animation: fadeIn 0.3s;
    }

    .help-section.active {
        display: block;
    }

    /* While searching, every section is open, so a match anywhere shows. */
    .help-content.help-searching .help-section {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .help-card {
        background: #2d3748;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        border: 1px solid rgba(102, 126, 234, 0.2);
    }

    .help-card h3 {
        color: #667eea !important;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .help-card h4 {
        color: #9ca3af !important;
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 1.1rem;
    }

    .help-card h5 {
        color: #e2e8f0 !important;
        margin-top: 15px;
        margin-bottom: 8px;
    }

    .help-card p {
        color: #d1d5db !important;
        line-height: 1.6;
    }

    .help-card ul, .help-card ol {
        color: #d1d5db !important;
        line-height: 1.8;
        margin-left: 20px;
    }

    .help-card strong {
        color: #e2e8f0 !important;
    }

    .help-card li {
        color: #d1d5db !important;
    }

    .help-card code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 3px;
        color: #fbbf24 !important;
        font-size: 0.9em;
    }

    .help-card pre {
        background: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        color: #d1d5db !important;
    }

    .step-by-step {
        counter-reset: step-counter;
        list-style: none;
        padding-left: 0;
    }

    .step-by-step li {
        counter-increment: step-counter;
        margin-bottom: 20px;
        padding-left: 50px;
        position: relative;
        color: #d1d5db !important;
    }

    .step-by-step li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .info-box,
    .warning-box,
    .success-box {
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
        color: #d1d5db !important;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        flex-wrap: wrap;
    }

    .info-box {
        background: rgba(23, 162, 184, 0.15);
        border-left: 4px solid #17a2b8;
    }

    .warning-box {
        background: rgba(255, 193, 7, 0.15);
        border-left: 4px solid #ffc107;
    }

    .success-box {
        background: rgba(28, 200, 138, 0.15);
        border-left: 4px solid #1cc88a;
    }

    .info-box > i,
    .warning-box > i,
    .success-box > i {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .info-box > i { color: #17a2b8; }
    .warning-box > i { color: #ffc107; }
    .success-box > i { color: #1cc88a; }

    /* Override the inherited .help-card h4/h5/p/ul/ol/small/strong colours
       so text inside a tinted box has high contrast. The default
       .help-card h4 (#9ca3af medium grey) is barely readable on a
       green/blue/yellow 15%-opacity tint background; this lifts every
       text element inside a tinted box to a near-white that pops against
       any of the three background tints uniformly.
       NB: covers BOTH our custom .info-box/.warning-box/.success-box
       classes AND Bootstrap's .alert.alert-info/.alert-secondary/etc.
       (the help blade uses both patterns in practice). */
    .info-box h3, .info-box h4, .info-box h5,
    .warning-box h3, .warning-box h4, .warning-box h5,
    .success-box h3, .success-box h4, .success-box h5,
    .alert h3, .alert h4, .alert h5 {
        color: #f9fafb !important;
    }
    .info-box p, .info-box ul, .info-box ol, .info-box li, .info-box small,
    .warning-box p, .warning-box ul, .warning-box ol, .warning-box li, .warning-box small,
    .success-box p, .success-box ul, .success-box ol, .success-box li, .success-box small,
    .alert p, .alert ul, .alert ol, .alert li, .alert small {
        color: #f3f4f6 !important;
    }
    .info-box strong, .warning-box strong, .success-box strong,
    .alert strong {
        color: #ffffff !important;
    }
    /* Bootstrap's `.text-muted` is `color: #6c757d !important` and ties
       on specificity with the box overrides above, so cascade order
       decides which wins: and Bootstrap usually loads later. Force
       text-muted inside tinted boxes to respect the box's contrast
       baseline by bumping specificity (chained class selector). */
    .info-box .text-muted,
    .warning-box .text-muted,
    .success-box .text-muted,
    .alert .text-muted,
    .info-box small.text-muted,
    .warning-box small.text-muted,
    .success-box small.text-muted,
    .alert small.text-muted {
        color: #d1d5db !important;
        opacity: 0.95;
    }

    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .feature-item {
        background: rgba(102, 126, 234, 0.1);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid rgba(102, 126, 234, 0.3);
    }

    /* Scoped to direct-child icons only (the big feature-card top icon).
       Without the "> i" combinator this selector also hits nested <i>
       elements inside badges, forcing badge icons to render indigo
       regardless of the badge background: barely visible on a green
       badge-success or red badge-danger. Direct-child leaves badge /
       inline icons alone so they inherit the badge's white text. */
    .feature-item > i {
        font-size: 2rem;
        color: #667eea;
        margin-bottom: 10px;
    }

    .feature-item h5 {
        color: #e2e8f0 !important;
        margin-bottom: 8px;
    }

    .feature-item p {
        color: #9ca3af !important;
        font-size: 0.9rem;
        margin: 0;
    }

    .search-box {
        position: relative;
        margin-bottom: 20px;
    }

    .search-box input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        background: #2d3748;
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 8px;
        color: #e2e8f0 !important;
    }

    .search-box i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 20px 0;
    }

    .quick-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        color: white;
        text-decoration: none;
        transition: transform 0.2s;
    }

    .quick-link:hover {
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
    }

    .quick-link i {
        font-size: 2rem;
        display: block;
        margin-bottom: 8px;
    }

    .faq-item {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        margin-bottom: 10px;
        overflow: hidden;
    }

    .faq-question {
        padding: 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
        color: #e2e8f0 !important;
    }

    .faq-question:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    .faq-question i {
        transition: transform 0.3s;
    }

    .faq-item.open .faq-question i {
        transform: rotate(180deg);
    }

    .faq-answer {
        padding: 0 15px;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s;
        color: #d1d5db !important;
    }

    .faq-item.open .faq-answer {
        padding: 15px;
        max-height: 500px;
    }

    .plugin-info-table {
        width: 100%;
        color: #d1d5db !important;
    }

    .plugin-info-table td {
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .plugin-info-table td:first-child {
        color: #9ca3af !important;
        width: 120px;
        font-weight: 600;
    }

    .plugin-info-table td:last-child {
        color: #e2e8f0 !important;
    }

    /* Earlier updates of the same major version, folded away under the latest
       one in the Recent changes box. */
    .recent-changes-box details {
        margin-top: 18px;
        padding-top: 10px;
        border-top: 1px solid rgba(139, 156, 247, 0.25);
    }

    .recent-changes-box summary {
        color: #8b9cf7 !important;
        font-weight: 600;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .help-wrapper {
            flex-direction: column;
        }

        .help-sidebar {
            position: relative;
            max-height: none;
        }

        .feature-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('full')
<div class="ratting-manager-wrapper ratting-manager-dashboard help-page">
    <div class="help-wrapper">
        {{-- Sidebar Navigation --}}
        <div class="help-sidebar">
            <div class="card card-dark">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-compass"></i> {{ trans('ratting-manager::help.navigation') }}</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column help-nav">
                        @foreach([
                            'overview' => ['fa-home', 'overview'],
                            'getting-started' => ['fa-rocket', 'getting_started'],
                            'my-ratting' => ['fa-crosshairs', 'my_ratting'],
                            'corporation' => ['fa-building', 'corporation'],
                            'monthly-report' => ['fa-file-invoice-dollar', 'monthly_report'],
                            'leaderboard' => ['fa-trophy', 'leaderboard'],
                            'ratter-lookup' => ['fa-search', 'ratter_lookup'],
                            'figures' => ['fa-calculator', 'figures'],
                            'wallet-tokens' => ['fa-key', 'wallet_tokens'],
                            'notifications' => ['fa-bell', 'notifications'],
                            'settings' => ['fa-cog', 'settings'],
                            'commands' => ['fa-terminal', 'commands'],
                            'data-esi' => ['fa-database', 'data_esi'],
                            'permissions' => ['fa-user-shield', 'permissions'],
                            'custom-styling' => ['fa-paint-brush', 'custom_styling'],
                            'faq' => ['fa-question-circle', 'faq'],
                            'troubleshooting' => ['fa-wrench', 'troubleshooting'],
                            'admin-diagnostics' => ['fa-stethoscope', 'admin_diagnostics'],
                        ] as $section => [$icon, $label])
                            <li class="nav-item">
                                <a href="#" class="nav-link {{ $loop->first ? 'active' : '' }}" data-section="{{ $section }}">
                                    <i class="fas {{ $icon }}"></i> {{ trans('ratting-manager::help.' . $label) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Content Area --}}
        <div class="help-content">

            {{-- Search Box --}}
            <div class="search-box">
                <input type="text" id="helpSearch" placeholder="{{ trans('ratting-manager::help.search_placeholder') }}" class="form-control">
                <i class="fas fa-search"></i>
            </div>

            {{-- Overview --}}
            <div id="overview" class="help-section active">
                {{-- Plugin Information --}}
                <div class="help-card">
                    <h3><i class="fas fa-info-circle"></i> Plugin Information</h3>
                    <p>
                        Version:
                        <img src="https://img.shields.io/packagist/v/mattfalahe/ratting-manager?label=release&color=667eea" alt="Version" style="vertical-align: middle;">
                        <img src="https://img.shields.io/badge/SeAT-5.0-764ba2" alt="SeAT 5.0" style="vertical-align: middle;">
                    </p>
                    <p>License: GPL-2.0-or-later</p>
                    <p>
                        <i class="fas fa-user"></i> <strong>Author:</strong> Matt Falahe<br>
                        <i class="fas fa-envelope"></i> <a href="mailto:mattfalahe@gmail.com" style="color: #667eea;">mattfalahe@gmail.com</a>
                    </p>

                    <div class="info-box">
                        <i class="fas fa-box-open"></i>
                        <div>Ratting Manager 1.0 is in preparation: there is no tagged release on Packagist yet.</div>
                    </div>

                    <div class="quick-links" style="margin-top: 15px;">
                        <a href="https://github.com/MattFalahe/Ratting-Manager" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                            <i class="fab fa-github" style="font-size: 1rem; margin-bottom: 4px;"></i>
                            GitHub Repository
                        </a>
                        <a href="https://github.com/MattFalahe/Ratting-Manager/blob/main/CHANGELOG.md" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                            <i class="fas fa-list" style="font-size: 1rem; margin-bottom: 4px;"></i>
                            Full Changelog
                        </a>
                        <a href="https://github.com/MattFalahe/Ratting-Manager/issues" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                            <i class="fas fa-bug" style="font-size: 1rem; margin-bottom: 4px;"></i>
                            Report Issues
                        </a>
                        <a href="https://github.com/MattFalahe/Ratting-Manager/blob/main/README.md" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                            <i class="fas fa-book" style="font-size: 1rem; margin-bottom: 4px;"></i>
                            README
                        </a>
                    </div>

                    <div class="success-box" style="margin-top: 20px;">
                        <i class="fas fa-heart"></i>
                        <div>
                            <strong>Support the Project:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>⭐ Star the GitHub repository</li>
                                <li>🐛 Report bugs and issues</li>
                                <li>💡 Suggest new features</li>
                                <li>🔧 Contribute code improvements</li>
                                <li>🌟 Share with other SeAT users</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Version Status: installed against the latest release on Packagist,
                     the same card as the other plugins' Help pages. --}}
                @php
                    $vs = $versionStatus;
                    $statusBadgeClass = [
                        'current'    => 'badge-success',
                        'outdated'   => 'badge-warning',
                        'ahead'      => 'badge-info',
                        'dev_branch' => 'badge-info',
                        'unknown'    => 'badge-secondary',
                    ][$vs['status']] ?? 'badge-secondary';
                    $statusLabel = [
                        'current'    => '✓ Up to date',
                        'outdated'   => '⚠ Update available',
                        'ahead'      => '🚀 Pre-release',
                        'dev_branch' => '🌱 Development branch',
                        'unknown'    => 'Unable to check',
                    ][$vs['status']] ?? 'Unknown';
                    // A branch ref is shown as it is; a tagged version gets the v.
                    $installedDisplay = $vs['is_dev_branch'] || $vs['current_source'] !== 'composer' ? $vs['current'] : ('v' . $vs['current']);
                    $sourceHint = $vs['current_source'] === 'composer'
                        ? "resolved via Composer's installed.json"
                        : 'not known: Composer does not say which version is installed';
                @endphp
                <div class="help-card">
                    <h3><i class="fas fa-tag"></i> Version Status</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 0.5rem 0;">
                        <div>
                            <strong>Installed:</strong>
                            <span class="badge badge-secondary" style="font-size: 0.9rem;" title="{{ $sourceHint }}">{{ $installedDisplay }}</span>
                        </div>
                        <div>
                            <strong>Latest release:</strong>
                            @if($vs['latest'])
                                <span class="badge badge-secondary" style="font-size: 0.9rem;">v{{ $vs['latest'] }}</span>
                            @elseif(!$vs['released'])
                                <span class="badge badge-secondary" style="font-size: 0.9rem;">none yet</span>
                            @else
                                <span class="badge badge-secondary" style="font-size: 0.9rem;">unknown</span>
                            @endif
                        </div>
                        <div>
                            <span class="badge {{ $statusBadgeClass }}" style="font-size: 0.9rem;">{{ $statusLabel }}</span>
                        </div>
                        @if($vs['release_url'])
                            <div>
                                <a href="{{ $vs['release_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-rm-primary">
                                    <i class="fas fa-external-link-alt"></i> View release notes
                                </a>
                            </div>
                        @endif
                    </div>
                    <small class="text-muted">{{ $vs['message'] }}</small>
                    @if($vs['status'] === 'outdated')
                        <div class="info-box" style="margin-top: 0.75rem;">
                            <i class="fas fa-arrow-circle-up"></i>
                            <strong>Upgrade recipe (SeAT Docker stack):</strong>
                            <pre style="margin-top: 0.4rem; margin-bottom: 0;"><code>docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d</code></pre>
                            <small class="text-muted" style="display: block; margin-top: 0.4rem;">
                                Container boot pulls the latest plugin via composer, runs new migrations, and re-seeds schedules automatically.
                            </small>
                        </div>
                    @endif
                    <small class="text-muted" style="display: block; margin-top: 0.4rem; font-size: 0.75rem;">
                        <i class="fas fa-info-circle"></i>
                        Installed version {{ $sourceHint }}. Latest checked via Packagist's public API (6h cache, safe on outages).
                    </small>
                </div>

                {{-- Welcome --}}
                <div class="help-card">
                    <h3><i class="fas fa-crosshairs"></i> Welcome to Ratting Manager</h3>
                    <p>Who rats, where, when, how much they earn and what they kill, for every member of the corporations you track, read from the wallet journals SeAT already pulls.</p>
                </div>

                {{-- What's New in v1 is the picture of the whole major version and never
                     names a point release. Later updates go in a Recent changes box under
                     it; the changelog keeps the full record. --}}
                <div class="whats-new-box">
                    <h4><i class="fas fa-crosshairs"></i> What's New in v1 <span class="whats-new-tag">first release</span></h4>
                    <p>Version 1 is the first Ratting Manager. It reads the wallet journals SeAT already pulls and turns them into:</p>
                    <ul>
                        <li><strong>My Ratting</strong> for every member: income, ISK per hour, hours, sessions, systems, what they kill and their personal records.</li>
                        <li><strong>A Corporation tab</strong> for directors: totals, the leaderboard with every player's ISK and ISK per hour, efficiency by system, when the corporation rats, who has gone quiet and who rats without a wallet token.</li>
                        <li><strong>A Monthly Report</strong> for directors: ISK ratted, the corporation's cut and everyone who ratted for it that month, ex-members included, marked with the day they left.</li>
                        <li><strong>A leaderboard</strong> members see too, with rank and hours only.</li>
                        <li><strong>Special spawns</strong>: officers, commanders, clone soldiers and capital rats, listed as they are killed.</li>
                        <li><strong>Members with no wallet token still count.</strong> The corporation journal names the ratter on every tick it taxes, and their income is worked out exactly from the corporation's cut.</li>
                        <li><strong>Insights</strong> for members and directors: favourite and best-paying systems, the most active and best-paying days and times, ISK per hour in every system ratted, and the NPCs killed most.</li>
                        <li><strong>Month by month</strong>: what each of a member's characters earned in a month with the total, and for directors, what the players earned against what landed in the corporation's wallet.</li>
                        <li><strong>Who has left, and who is not on SeAT</strong>: everyone who rats is watched for leaving, token or no token, with the time they went and their last tick, and a badge on anyone SeAT holds no token for.</li>
                        <li><strong>Discord and Slack notifications</strong>: alerts for special spawns, ratting ships lost, outsiders in home space, stalled data, untracked ratters, lost wallet tokens and players gone quiet, and weekly and monthly Director Reports and Rankings.</li>
                    </ul>
                </div>

                {{-- What is Ratting Manager? --}}
                <div class="help-card">
                    <h3><i class="fas fa-info-circle"></i> What is Ratting Manager?</h3>
                    <p>
                        Ratting Manager reads the bounty ticks and ESS payouts in the corporation and character wallet journals SeAT pulls,
                        pairs the two, and turns them into personal and corporation dashboards, a leaderboard and Discord or Slack notifications.
                        Every member sees their own ratting, and directors see the same for the whole corporation.
                    </p>
                    <div class="info-box">
                        <i class="fas fa-lightbulb"></i>
                        <strong>Key Benefit:</strong>
                        Members who never added a wallet token still count. The corporation journal names the ratter on every tick it taxes, and their income is worked out exactly from the corporation's cut.
                    </div>
                </div>

                {{-- Key Features --}}
                <div class="help-card">
                    <h3><i class="fas fa-star"></i> Key Features</h3>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fas fa-user"></i>
                            <h5>My Ratting</h5>
                            <p>Income, ISK per hour, hours, rats, rank by hours and the tax paid, with charts, insights and personal records.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-building"></i>
                            <h5>Corporation tab</h5>
                            <p>Totals, every player's ISK and ISK per hour, efficiency by system, when the corporation rats, and who is new, back or gone quiet.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h5>Monthly Report</h5>
                            <p>One calendar month for the books: ISK ratted, the corporation's cut and who ratted, ex-members marked with the day they left.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-trophy"></i>
                            <h5>Leaderboard</h5>
                            <p>Rank and hours for members. Every column for directors, with a CSV download.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-eye-slash"></i>
                            <h5>Ratters without tokens</h5>
                            <p>Ticks only the corporation journal has are worked out from the corporation's cut, at that day's tax rate.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-key"></i>
                            <h5>Lost wallet tokens</h5>
                            <p>Up to 30 days fill in by themselves once a token is back, and you are told the last day it can come back and lose nothing.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-skull"></i>
                            <h5>Special spawns</h5>
                            <p>Officers, commanders, clone soldiers, capital rats and NPCs on a watch list, listed as they die.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-lightbulb"></i>
                            <h5>Insights</h5>
                            <p>Favourite and best-paying systems, days and times, streaks, and the NPCs killed most.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-calendar-alt"></i>
                            <h5>Month by month</h5>
                            <p>Each character's month for members, and what the players earned against what the corporation took for directors.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-bell"></i>
                            <h5>Notifications</h5>
                            <p>Special spawns, ships lost, outsiders in home space, stalled data, untracked and quiet ratters, lost wallet tokens, and weekly and monthly reports.</p>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-stethoscope"></i>
                            <h5>Diagnostics</h5>
                            <p>Health checks, a master test, settings and data checks, a tick trace and notification testing, for admins.</p>
                        </div>
                    </div>
                </div>

                {{-- Quick Links, to the pages this reader can open --}}
                @if($isMember)
                    <div class="help-card">
                        <h3><i class="fas fa-rocket"></i> Quick Links</h3>
                        <div class="quick-links">
                            <a href="{{ route('ratting-manager.dashboard') }}" class="quick-link">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                            <a href="{{ route('ratting-manager.leaderboard') }}" class="quick-link">
                                <i class="fas fa-trophy"></i>
                                Leaderboard
                            </a>
                            @if($isDirector)
                                <a href="{{ route('ratting-manager.report') }}" class="quick-link">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    Monthly Report
                                </a>
                                <a href="{{ route('ratting-manager.ratters.index') }}" class="quick-link">
                                    <i class="fas fa-search"></i>
                                    Ratter Lookup
                                </a>
                            @endif
                            @if($isAdmin)
                                <a href="{{ route('ratting-manager.settings.index') }}" class="quick-link">
                                    <i class="fas fa-cog"></i>
                                    Settings
                                </a>
                                <a href="{{ route('ratting-manager.diagnostic') }}" class="quick-link">
                                    <i class="fas fa-stethoscope"></i>
                                    Diagnostics
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Getting Started --}}
            <div id="getting-started" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-rocket"></i> Getting Started</h3>
                    <p>
                        Ratting Manager is already installed: if you can read this page, the plugin and its tables are in place.
                        This is the least you need to set up to see ratting on the pages. Most of it has working defaults; the corporations
                        to track and who sees what are the decisions that need you.
                    </p>

                    <h4>Minimal Setup</h4>
                    <ol class="step-by-step">
                        <li>
                            <strong>Pick the corporations to track</strong><br>
                            Under Ratting Manager, Settings. Search by name or ticker, or open the list; picking one shows its ticker, alliance, tax rate, members, how many of its characters are on SeAT, and whether SeAT holds its bounty rows, and you confirm from there. Only ticks from tracked corporations are imported.
                        </li>
                        <li>
                            <strong>Check SeAT has a director token with wallet access</strong><br>
                            One for each tracked corporation. The corporation journal is what shows members who have not added a wallet token of their own.
                        </li>
                        <li>
                            <strong>Run the first import</strong><br>
                            Once, to bring in the last 365 days SeAT already holds, the most any page shows. Without it, the schedule brings in the last 31 days within a day.
                            <pre><code>docker exec -it seat-docker-front-1 php artisan ratting-manager:initialize</code></pre>
                        </li>
                        <li>
                            <strong>Give out the permissions</strong><br>
                            Member for everyone who rats, Director for the people who should see everyone's ISK in their own corporation, and Admin for whoever runs the plugin and reads every tracked corporation. See Permissions.
                        </li>
                        <li>
                            <strong>Set up notifications (optional)</strong><br>
                            Nothing is sent anywhere until an admin adds a webhook and ticks what it should carry. See Notifications.
                        </li>
                    </ol>

                    <div class="success-box">
                        <i class="fas fa-check-circle"></i>
                        <strong>Good to know:</strong>
                        A tick reaches the pages about one to two hours after it pays out: ESI caches the wallet journal for an hour, SeAT pulls it about hourly, and the plugin reads it every half hour. Figures are kept until the next import changes them, so a page shows the same figures between imports however often it is opened.
                    </div>
                </div>
            </div>

            {{-- My Ratting --}}
            <div id="my-ratting" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-crosshairs"></i> My Ratting</h3>
                    <p>Your own ratting across all your characters, or one of them with the character picker. The period picker covers this month, last month, the last 30 or 90 days and the last 12 months, any of the 11 calendar months before this one, or a custom range of days within the last year. A pick applies as soon as you make it; a custom range waits for its Show button, since it takes two dates.</p>
                    <ul>
                        <li><strong>Ratting income</strong> is what you received: bounties plus ESS payouts.</li>
                        <li><strong>Corporation tax paid</strong> is the corporation's cut of your bounties and ESS payouts, from the corporation journal, with the share of the payout it took. The income chart shows it month by month, and Month by character shows it per character.</li>
                        <li><strong>ISK per hour</strong> is that income over the time you spent ratting. Alts ratting together count their time once, so three characters for an hour is one hour.</li>
                        <li><strong>Rats killed</strong> counts each tick's kill list once. In a fleet that shares bounties, every member's tick lists the fleet's rats, so a shared payout counts them once and not once per member.</li>
                        <li><strong>Rank by hours</strong> is your place in your corporation by hours ratted.</li>
                        <li><strong>When I rat</strong> shades each hour of the week, in EVE time, by how often you ratted in it. Hover a square for the count.</li>
                        <li><strong>Sessions</strong> are runs of ticks with no gap longer than {{ $sessionGap }} minutes. A session's ISK per hour counts bounties only, because ESS pays out later and is not tied to one session.</li>
                    </ul>
                    <p>Long lists, such as sessions, systems and special spawns, show their first 10 rows. <strong>View all</strong> opens the whole list on its own page, where it can be searched and sorted.</p>
                    <h4>Insights</h4>
                    <ul>
                        <li><strong>Favourite system</strong> is where you spent the most time; <strong>best-paying system</strong> is where your ISK per hour was highest, among systems with at least an hour of ratting.</li>
                        <li><strong>Most active day</strong> and <strong>most active time</strong> are the day of the week and the three hours of the day, in EVE time, with the most ratting. The best-paying ones are where your bounty ISK per hour was highest.</li>
                        <li><strong>Streaks</strong> are days in a row with at least one tick. The current streak counts while you ratted today or yesterday.</li>
                        <li><strong>By day of the week</strong> and <strong>by hour of the day</strong> show your hours; hover a bar for its bounties and bounty ISK per hour. ESS is left out of these, because it pays out hours after the ratting it is for.</li>
                        <li><strong>ISK per hour by system</strong> and the <strong>Systems</strong> table count ESS, since each payout names the system its bank was in.</li>
                    </ul>
                    <h4>Month by character</h4>
                    <p>What each of your characters earned in a month: bounties, ESS, corporation tax, hours, ISK per hour, ticks and rats, with the month's total across all of them. The total's hours count alts ratting together once, so they can be less than the characters' hours added up. Below it, each character's income month by month.</p>
                </div>
            </div>

            {{-- Corporation --}}
            <div id="corporation" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-building"></i> The Corporation Tab</h3>
                    <p>Directors get a second tab on the Dashboard, for the tracked corporations their characters are in now. Admins get every tracked corporation. It loads when it is first opened, and has its own corporation and period pickers. It is split into sections for an overview, players, when, where, kills and month by month, and the section open stays open when the period changes.</p>
                    <ul>
                        <li><strong>ISK ratted</strong>: what the rats paid before tax. See How the Figures Work.</li>
                        <li><strong>Ratted in</strong>: how many systems, constellations and regions the period covers.</li>
                        <li><strong>Leaderboard</strong>: every player by income, with hours, ISK per hour, how that compares with the median player, rats, active days, the change on the period before, and coverage. The change is left out when the period before reaches back further than the imported ratting, as the year before Last 12 months usually does, since every player would show a jump from almost nothing.</li>
                        <li><strong>Efficiency by system</strong>: where the time pays best.</li>
                        <li><strong>When the corporation rats</strong>: how many players rat in each hour of the week, useful for planning cover.</li>
                        <li><strong>Ratters coming and going</strong>: new players, players back after a gap, players still ratting, and players who stopped since the month before.</li>
                        <li><strong>Untracked ratters</strong>: characters the corporation journal pays bounties to who have no wallet token on SeAT.</li>
                        <li><strong>Wallet tokens lost</strong>: characters who had a token and lost it, with the last day it can come back and lose nothing. Shown only when there are any. See When a Token Is Lost.</li>
                        <li><strong>Gone quiet</strong>: regular ratters who have stopped.</li>
                        <li>Each long list shows its first 10 rows; <strong>View all</strong> opens the whole of it, searchable and sortable, for the same corporation and period. The leaderboard's View all opens the Leaderboard page.</li>
                        <li><strong>Insights</strong>: the same as on My Ratting for the whole corporation, plus the most players ratting in one hour, players per active day and income per player. The Systems table adds how many players ratted in each system and who ratted there most.</li>
                        <li><strong>Month by month</strong>: for the last 12 months, what the players earned, what landed in the corporation's wallet and the tax rate that makes, players, characters, hours, ISK per hour and income per player.</li>
                    </ul>
                    <h4>Coverage</h4>
                    <p>
                        <span class="badge badge-coverage-full"><i class="fas fa-check"></i> Tracked</span> means the ticks the corporation journal saw are also in the character's own journal.
                        A percentage means some are. <span class="badge badge-coverage-none"><i class="fas fa-eye-slash"></i> Untracked</span> means none are, so the player's income is worked out from the corporation's cut.
                    </p>
                </div>
            </div>

            {{-- Monthly Report --}}
            <div id="monthly-report" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Monthly Report</h3>
                    <p>For directors: one calendar month for the corporation's books, for this month or any of the 11 before it. It opens on the last full month.</p>
                    <ul>
                        <li><strong>ISK ratted</strong>, with the change on the month before; the <strong>corporation's cut</strong> and its share; and what the <strong>pilots received</strong>, in bounties and ESS.</li>
                        <li>How many players ratted and how many of them have since left, hours, the median ISK per hour, rats, and how many systems, constellations and regions it covers.</li>
                        <li><strong>Who ratted</strong>, biggest first by ISK ratted, searchable and sortable, with a CSV download that adds up to its total line.</li>
                        <li>The <strong>top systems</strong> of the month.</li>
                    </ul>
                    <p>Ratting counts for the corporation a character was in at the time, so a past month still lists everyone who ratted for it, including people who have left since. They carry an ex-member tag with the day they left, and the time in EVE time when you hover it. The figures come from the summaries, so a month whose raw ticks have been dropped reports the same.</p>
                </div>
            </div>

            {{-- Leaderboard --}}
            <div id="leaderboard" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-trophy"></i> Leaderboard</h3>
                    <p>Members see their corporation ranked by hours ratted, with active days, and their own row picked out. Nobody's ISK is shown to members, their own included; they find their own on My Ratting.</p>
                    <p>Directors see the full board: ISK ratted, income, hours, ISK per hour, the median comparison, rats, active days, change and coverage, for the tracked corporations their characters are in now. Admins see it for any tracked corporation or all of them.</p>
                    <h4>Players who have left</h4>
                    <p>
                        Ratting counts for the corporation a character was in at the time, so a player who leaves stays on the board for the periods they ratted.
                        <span class="rm-left-tag" style="margin-left: 0;">{{ trans('ratting-manager::common.left') }}</span> beside a name means none of their characters is in the corporations shown now, with the day they left. Hover it for the time and their last tick.
                        The time comes from EVE's corporation history: SeAT's copy first, then ESI and EVEWho for characters SeAT holds no token for. They are looked up hourly in the background, so a tag with no date is waiting for that. A tag that says gone by means no history has answered yet: it is when the move was first known of, and it is asked again for a week.
                        It shows on the Monthly Report, the leaderboard, the Corporation tab, Gone quiet and the Ratter Lookup.
                    </p>
                    <p>Everyone who rats is watched for leaving, token or no token. SeAT refreshes every character's corporation about every two hours from a public ESI call, so a revoked token does not stop it, and a director's member tracking drops anyone who has gone. For a ratter member tracking does not list, the plugin also asks ESI itself once a day, with EVEWho and zKillboard behind that, and the newest answer wins. EVEWho and zKillboard can be days behind, so one of theirs only overrules SeAT once SeAT's answer has stood for a week.</p>
                    <p>A tick from before they left still counts when it lands after they have gone: a corporation journal row carries the time the tick paid and names the ratter, so it goes to the corporation that took the cut, whenever SeAT got round to it. A tick after they left is taxed by their new corporation, so it never lands in this one's wallet, and in their own journal a taxed row names who took the tax, so it is not credited here even while SeAT's history is a day behind. A corporation journal row is only written when the corporation took a cut, so a tick under 100,000 ISK, or a corporation at 0%, shows nothing there.</p>
                    <h4>Players not on SeAT</h4>
                    <p>
                        <span class="rm-auth-tag" style="margin-left: 0;">{{ trans('ratting-manager::common.not_authed') }}</span> beside a name means SeAT holds no live token for any of their characters. Hover it to see whether they were never added or have lost every token. They still count: their time and rats come from the corporation journal, with their income worked out from the corporation's cut. It shows wherever the ex-member tag does, and in the CSVs.
                    </p>
                    <h4>CSV download</h4>
                    <p>
                        Directors can download the board as a CSV for the same corporation and period, ranked the same way, with income split into bounties, ESS and the part worked out from the corporation's cut,
                        ISK ratted, the corporation's tax, hours, ISK per hour, rats, active days, change, coverage and when the player left.
                        ISK is written as plain numbers so a spreadsheet can add them up.
                    </p>
                </div>
            </div>

            {{-- Ratter Lookup --}}
            <div id="ratter-lookup" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-search"></i> Ratter Lookup</h3>
                    <p>For directors: every player who ratted for the corporations they may read in the last 90 days, with their characters, income, hours, ISK per hour, last tick, coverage and whether they have a wallet token. Search or sort it, and open a player.</p>
                    <h4>A player's page</h4>
                    <ul>
                        <li>Their ratting as they see it on My Ratting, with the same period picker, including their <strong>rank by hours</strong> in their corporation.</li>
                        <li><strong>Where the figures come from</strong>: for each character, the corporation it is in now, whether it has a wallet token, and its ticks in the last 90 days that were paired, seen only in the character's own journal, or seen only in the corporation journal.</li>
                        <li>A player who has left is marked, as on the leaderboard, and a character who has lost a wallet token says since when and the last day it can come back and lose nothing.</li>
                        <li>A director only sees what the player ratted for the corporations they may read, and cannot open a player who has nothing to do with them.</li>
                    </ul>
                </div>
            </div>

            {{-- How the figures are worked out --}}
            <div id="figures" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-calculator"></i> How the Figures Work</h3>
                    <h4>Two journals, one tick</h4>
                    <p>
                        Every 20 minutes of ratting pays one tick. The <strong>corporation journal</strong> records the corporation's cut of it and names the ratter.
                        The <strong>character journal</strong> records what the character received, if the character has a wallet token on SeAT.
                        Both carry the same time, the system and the list of rats killed, so the plugin pairs them.
                    </p>
                    <h4>Worked-out income</h4>
                    <p>
                        When only the corporation journal has a tick, the character's income is worked out from the cut:
                        <code>cut x (1 - rate) / rate</code>. At 15% corporation tax, a cut of 1.5M ISK means the character received 8.5M ISK.
                        The rate for each day is read back from that day's paired ticks, so a change of tax rate is followed.
                        Worked-out figures carry a dashed underline and appear striped in the charts.
                    </p>
                    <h4>ISK ratted</h4>
                    <p>What the rats paid before tax: what the pilots received plus the corporation's cut. The game charges no corporation tax on a payout under 100,000 ISK, and a 0% corporation takes none at all, so those were received whole and count as they are. It is worked out when a page is read, from the summaries, so it needs nothing rebuilt and stands after raw ticks are dropped.</p>
                    <h4>ESS payouts</h4>
                    <p>The ESS main bank pays out later, as its own journal row, and the corporation takes its cut of that too. ESS payouts are paired and worked out the same way as ticks, and count towards income on the day they pay out.</p>
                    <h4>Time</h4>
                    <p>Each tick stands for the 20 minutes before it. A player's time is every stretch any of their characters was ratting, counted once.</p>
                    <h4>Names</h4>
                    <p>
                        A member who never added a token is not in SeAT at all. Their name comes from the journal row itself
                        (<em>Encounter Surveillance System in ... transferred funds to {name}</em>), and the background lookup adds the
                        corporation they are in now, from ESI, or from EVEWho or zKillboard when ESI is down. Pages only ever read what
                        has been found. A character nobody has named yet shows as <em>Character info in progress</em>, and the page
                        refreshes itself once the lookup has them, usually within a minute.
                    </p>
                    <h4>NPCs</h4>
                    <p>Each rat is placed by faction, size and rank from SeAT's SDE. A group that says nothing of size goes by the NPC's mass, so a hauler with Carrier in its name is not taken for a capital. Anything that cannot be placed still counts, as Other, and Diagnostics lists it, leaving out the Crimson Harvest event's rats and the ones the game marks with a diamond.</p>
                    <p>The rank comes from the SDE group: officers and commanders have groups of their own, the faction-named belt rats such as Domination and Shadow Serpentis among the commanders. A rat with Commander or Officer only in its own name, such as Angel Commander, is a regular rat. Clone soldiers, which always drop a security tag, are told apart by name. An officer always drops officer modules and a dog tag, and a commander can drop faction modules. Capital rats drop nothing out of the ordinary but are listed for how rarely they spawn: carriers and supercarriers for rogue drones, dreadnoughts and titans for pirates, and dreadnoughts and supercarriers for Sansha's Nation.</p>
                    <p>The overseer of a DED complex, such as Tritan in the Angel Military Operations Complex, is killed every time the site is run, so it stays a regular rat. Add its type ID under Extra NPC type IDs to have it listed.</p>
                </div>
            </div>

            {{-- When a token is lost --}}
            <div id="wallet-tokens" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-key"></i> When a Token Is Lost</h3>
                    <p>Tokens get revoked and lost. Most of the time this costs nothing, because there are two journals and the plugin keeps reading whichever is coming in.</p>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><strong>A member's wallet token</strong></td><td>The corporation journal still shows every tick it taxed, with the income worked out from the cut, so hours, rats and income carry on. A tick too small to tax is not seen until the token is back.</td></tr>
                            <tr><td><strong>A director's token</strong></td><td>Members with their own token carry on, and each taxed bounty row says what the corporation took, so its cut stays exact. Members with no token are not seen until it is back. The Data stalled alert says so.</td></tr>
                            <tr><td><strong>Back within 30 days</strong></td><td>SeAT walks the journal back to where it stopped, and the next import, within half an hour, reaches back for those days and pairs them. Nothing is lost, to the ISK. There is nothing to run.</td></tr>
                            <tr><td><strong>Back later than that</strong></td><td>ESI serves 30 days of journal, so older days cannot come back from anywhere. Ticks the corporation taxed stay worked out, which is the same sum at that day's rate. Ticks only the lost journal had stay unseen.</td></tr>
                        </tbody>
                    </table>
                    <h4>Knowing in time</h4>
                    <p>The clock starts when the token goes. <strong>Wallet tokens lost</strong> on the Corporation tab lists who has lost one, since when, what is being worked out meanwhile, and the last day it can come back and lose nothing. Data Integrity says the same, and the <strong>Wallet token lost</strong> alert posts when it happens and again a week before that day. It also catches a token that looks fine while its journal has stopped coming in.</p>
                </div>
            </div>

            {{-- Notifications --}}
            <div id="notifications" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                    <p>Ratting Manager posts to Discord webhooks, and to Slack incoming webhooks as plain text. Nothing is sent anywhere until an admin adds a webhook and ticks what it should carry.</p>
                    <h4>Setting up</h4>
                    <ol class="step-by-step">
                        <li><strong>Add a webhook</strong> under Settings, Webhooks: a name, the URL from Discord, whether it is for one corporation or all tracked corporations, and the notifications it carries. A webhook for one corporation only gets that corporation's posts. Its edit button changes any of these later. The URL is stored encrypted and never shown again; left empty when editing, it stays as it is.</li>
                        <li><strong>Set who gets mentioned</strong> under Settings, Notifications. Each notification has a switch, a default role, and a row per webhook with a tick and a role of its own.</li>
                        <li><strong>Check the Routing Map</strong>: where each notification goes now and who it mentions.</li>
                        <li><strong>Send a sample</strong> of any notification to any webhook from Diagnostics, Notification Testing, to see it as it will look.</li>
                    </ol>
                    <h4>Who gets mentioned</h4>
                    <p>The role set on the webhook's own row comes first. Left blank, the notification's default role is used. <em>No ping</em> mentions nobody, even when a default is set. Only the role or user named is ever pinged; anything else typed in a role field is ignored. With SeAT Broadcast or SeAT Connector installed, roles can be picked by name.</p>
                    <h4>Alerts</h4>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><strong>Special spawn</strong></td><td>An NPC of the chosen rank or above (clone soldier, commander, officer), a capital rat, or an NPC on the watch list, killed by a tracked ratter. One post per spawn, however many fleet members shared it.</td></tr>
                            <tr><td><strong>Ratting ship lost</strong></td><td>A tracked pilot lost a ship in the system they had a tick in shortly before. Needs SeAT to hold the killmail. Links zKillboard. The final blow is named from SeAT, or looked up from ESI, EVEWho or zKillboard when SeAT does not know the pilot. Tractor units and other deployables are not ships and are not posted.</td></tr>
                            <tr><td><strong>Outsiders in home space</strong></td><td>ESI's hourly count of NPCs killed in a system of the home space, less what the tracked corporations killed there in that hour. The home space is the regions, constellations and systems set on the alert under Settings, Notifications; with none set it sends nothing, even when switched on. Sent a few hours after the hour, once the journals for it are in.</td></tr>
                            <tr><td><strong>Data stalled</strong></td><td>A corporation's wallet journal has had no new row for the hours set, with the last day a token can come back and lose nothing, or the import has not run for three hours. Posts again when it is back.</td></tr>
                            <tr><td><strong>Untracked ratter</strong></td><td>Ratters the corporation journal pays with no wallet token on SeAT, in one post per corporation. Each is posted once.</td></tr>
                            <tr><td><strong>Wallet token lost</strong></td><td>A ratter's token is lost or revoked, or their journal has stopped coming in, in one post per corporation, with the last day it can come back and lose nothing. Again a week before that day. Posts again if it is lost again after coming back.</td></tr>
                            <tr><td><strong>Ratter gone quiet</strong></td><td>Players newly on the Gone quiet list, in one post per corporation. A player who rats again leaves the list.</td></tr>
                        </tbody>
                    </table>
                    <p>Each alert remembers what it has sent and never sends the same thing twice. A post every bound webhook refused is tried again on the next run.</p>
                    <h4>Reports</h4>
                    <ul>
                        <li><strong>Director Report</strong>, weekly on Monday and monthly on the 1st, per corporation: what the players earned, what landed in the corporation's wallet and the tax rate, the change on the period before, players, hours, median ISK per hour, rats, the top systems, and players ranked by income (tax paid beside it), hours, ISK per hour and corporation tax paid. For director channels.</li>
                        <li><strong>Rankings</strong>, on the same days: players ranked by hours ratted, with no ISK figures, so it can go to a members' channel.</li>
                    </ul>
                    <p>Players are ranked as a main with all their alts. A report goes once per period; one that could not be sent, or that had no webhook yet, is sent on the next day's run.</p>
                </div>
            </div>

            {{-- Settings --}}
            <div id="settings" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-cog"></i> Settings</h3>
                    <ul>
                        <li><strong>Corporations to track</strong>: only these are imported. Stop tracking keeps what was imported but hides it, and imports nothing new.</li>
                        <li><strong>Session gap</strong>: how long a pause ends a session. The default of {{ $sessionGap }} minutes lets one missed tick pass.</li>
                        <li><strong>Gone quiet after</strong> and <strong>Regular ratter window</strong>: who is listed under Gone quiet.</li>
                        <li><strong>Leaderboard size for members</strong>: how many places members see.</li>
                        <li><strong>Keep raw ticks for</strong>: 0 keeps them for the 365 days the pages can show. From 45 days up, ticks, kill rows, ESS payouts and daily kill totals older than this are deleted nightly once the summaries hold their days. Every page and report keeps its figures, but those days can only be imported again with <code>ratting-manager:restore-ticks</code>. See Data and ESI.</li>
                        <li><strong>Close the pages to people who have left</strong>: when on, the member and director pages also need a character in a tracked corporation now, so a player who leaves loses them. Off by default. Help stays open, and admins are never closed out.</li>
                        <li><strong>Extra NPC type IDs</strong>: rats to list as special spawns on top of officers, commanders, clone soldiers and capital rats.</li>
                        <li><strong>Home space</strong>, on the Outsiders alert under Notifications: the regions, constellations and systems the alert watches, by name or ID. Until one is set the alert sends nothing and carries a warning badge.</li>
                        <li><strong>Webhooks</strong>, <strong>Notifications</strong> and the <strong>Routing Map</strong>: see Notifications. Notifications also holds the alert thresholds: the lowest special spawn rank, how old a spawn may be, when a journal counts as stalled, how many NPC kills count as outsiders, how close a tick has to be to a loss, and how many players each ranking lists.</li>
                    </ul>
                </div>
            </div>

            {{-- Commands --}}
            <div id="commands" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-terminal"></i> CLI Commands</h3>
                    <p>Run them inside SeAT's front container, for example <code>docker exec -it seat-docker-front-1 php artisan ratting-manager:process</code>. Every command that writes takes the same lock, so a manual run never overlaps the scheduled one.</p>

                    <h4><i class="fas fa-clock text-primary"></i> On the Schedule</h4>
                    <p>These run by themselves. The schedules are added once, and changes you make to them in SeAT are kept on update. You can also run any of them by hand. Times are EVE time.</p>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Command</th>
                                    <th>Schedule</th>
                                    <th>Description &amp; Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>ratting-manager:process</code></td>
                                    <td><span class="badge badge-info">Every 30 min</span> :10 and :40</td>
                                    <td>Reads the last two days of both journals, pairs the rows, works out the income of corporation-only rows and builds the sessions, summaries and character days of what changed. Rows SeAT added since the last run can be days old, when a token has come back or a tick landed late, so it reaches back for them by itself and rebuilds only those days.<br>
                                        <small class="text-muted">Options: <code>--days=2</code> how many days back to read, <code>--from=YYYY-MM-DD</code> read from this date instead</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:process --days=31</code></td>
                                    <td><span class="badge badge-primary">Daily</span> 04:25</td>
                                    <td>The same over the last 31 days, as a backstop.</td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:resolve-characters --requested</code></td>
                                    <td><span class="badge badge-danger">Every minute</span></td>
                                    <td>Looks up the characters pages asked for, which show as <em>Character info in progress</em> until then, so no page waits on ESI.</td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:resolve-characters</code></td>
                                    <td><span class="badge badge-info">Every 10 min</span></td>
                                    <td>Also looks up every ratter of the last year SeAT's member tracking does not list, once a day, gathered so a day of them is one request. ESI comes first, then EVEWho and zKillboard when ESI is down, and it stops calling ESI while the error budget it shares with SeAT is low.<br>
                                        <small class="text-muted">Options: <code>--requested</code> only the characters pages asked for, <code>--stats</code> show what the table holds and look nothing up</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:track-departures</code></td>
                                    <td><span class="badge badge-info">Hourly</span> :37</td>
                                    <td>Looks up exactly when ratters who have moved left their tracked corporation, in the background: SeAT's corporation history first, then ESI and EVEWho. Each move is looked up once, and one no history answered for is asked about again for a week.</td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:notify</code></td>
                                    <td><span class="badge badge-info">Every 30 min</span> :15 and :45</td>
                                    <td>Checks every alert, five minutes after the import, and sends what is new.<br>
                                        <small class="text-muted">Options: <code>--only=</code> one alert: <code>special_spawn</code>, <code>ship_lost</code>, <code>outsiders</code>, <code>data_stalled</code>, <code>untracked_ratter</code>, <code>wallet_lost</code> or <code>gone_quiet</code></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:poll-system-kills</code></td>
                                    <td><span class="badge badge-info">Hourly</span> :07</td>
                                    <td>Keeps ESI's hourly NPC kill counts for the systems in the home space, for the Outsiders alert. Calls nothing while no home space is set.</td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:send-reports</code></td>
                                    <td><span class="badge badge-primary">Daily</span> 11:20</td>
                                    <td>Sends the Director Reports and Rankings that are due. Each goes once per period, and one that could not be sent goes on the next day's run.<br>
                                        <small class="text-muted">Options: <code>--weekly</code> or <code>--monthly</code> one kind only, <code>--force</code> send the last period again</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:drop-old-ticks</code></td>
                                    <td><span class="badge badge-primary">Daily</span> 04:55</td>
                                    <td>Deletes ticks, kill rows, ESS payouts and daily kill totals older than Keep raw ticks in Settings, once the summaries hold their days, and everything older than what any page shows. See Data and ESI.<br>
                                        <small class="text-muted">Options: <code>--dry-run</code> say what would go and delete nothing</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mt-4"><i class="fas fa-magic text-success"></i> First Import</h4>
                    <p><code>docker exec -it seat-docker-front-1 php artisan ratting-manager:initialize</code></p>
                    <p>Run once, after picking the corporations. It checks the setup, imports the last 365 days of bounty ticks and ESS payouts SeAT already holds, the most any page shows, and builds every session, daily summary, character day and monthly summary from them, with a progress bar for each step. Without it, the schedule brings in the last 31 days within a day.</p>
                    <p><small class="text-muted">Options: <code>--from=YYYY-MM-DD</code> start later</small></p>

                    <h4 class="mt-4"><i class="fas fa-wrench text-info"></i> Run by Hand</h4>
                    <p>Not on the schedule. The first few are for when something needs looking at; the rest are the steps of <code>process</code> on their own, which it does for you.</p>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Command</th>
                                    <th>Description &amp; Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>ratting-manager:restore-ticks</code></td>
                                    <td>Imports dropped days again from SeAT's journals, up to a year back, for every tracked corporation. It always shows what would change first and only goes ahead when you type the confirmation, and it stops without changing anything if a day would lose ticks or income.<br>
                                        <small class="text-muted">Options: <code>--from=YYYY-MM-DD</code> required, how far back, <code>--dry-run</code> stop after showing it</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:trace-tick {journal_id}</code></td>
                                    <td>Shows what the plugin did with one journal row, the same as the Tick Trace tab.<br>
                                        <small class="text-muted">Arguments: <code>{journal_id}</code> the id of a bounty or ESS row in either wallet journal</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:update-daily-summaries</code></td>
                                    <td>Rebuilds the daily summaries, and the character days and monthly summaries built with them. Data Integrity names the day when it is needed.<br>
                                        <small class="text-muted">Options: <code>--days=2</code> how many days back to read, <code>--from=YYYY-MM-DD</code> read from this date instead</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:build-sessions</code></td>
                                    <td>Groups ticks into sessions.<br>
                                        <small class="text-muted">Options: <code>--days=2</code>, <code>--from=YYYY-MM-DD</code></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:import-corp-journal</code></td>
                                    <td>The corporation journal step of <code>process</code> on its own. What it writes is built by the next scheduled run.<br>
                                        <small class="text-muted">Options: <code>--days=2</code>, <code>--from=YYYY-MM-DD</code></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:import-character-journal</code></td>
                                    <td>The character journal step on its own, built the same way.<br>
                                        <small class="text-muted">Options: <code>--days=2</code>, <code>--from=YYYY-MM-DD</code></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:update-tax-rates</code></td>
                                    <td>Reads tax rates back from paired rows and works out corporation-only rows.<br>
                                        <small class="text-muted">Options: <code>--days=2</code>, <code>--from=YYYY-MM-DD</code></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:snapshot-tax-rates</code></td>
                                    <td>Keeps today's corporation tax rate, for days with no paired rows.</td>
                                </tr>
                                <tr>
                                    <td><code>ratting-manager:sync-npc-types</code></td>
                                    <td>Places NPC types not placed yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Data and ESI --}}
            <div id="data-esi" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-database"></i> Data and ESI</h3>

                    <h4>Late rows</h4>
                    <p>A row can reach SeAT long after the tick it records, when a token comes back or SeAT has been behind. The half-hourly <code>process</code> looks for rows SeAT added since its last run, however old they are, reaches back for them and rebuilds only those days: sessions, daily summaries, character days and monthly summaries. The nightly <code>process --days=31</code> goes over the last 31 days as a backstop. There is nothing to run.</p>

                    <h4>What is kept, and for how long</h4>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><strong>Ticks, kill rows, ESS payouts, daily kill totals</strong></td><td>Keep raw ticks under Settings, 45 days at the least, once the summaries hold their days. At 0, the 365 days below.</td></tr>
                            <tr><td><strong>Daily summaries, character days, sessions, special spawns, tax rates, departures</strong></td><td>365 days. No page shows further back.</td></tr>
                            <tr><td><strong>Monthly summaries</strong></td><td>25 months, so the last 12 can be compared with the 12 before them.</td></tr>
                        </tbody>
                    </table>
                    <p>Every chart comes from the summaries, today's rows included, since each import builds them as it writes, so dropping raw rows moves no figure. The character days hold each tick's second, system, ISK, rats and kill list, which is what lets alts and fleets be counted once for any period. Data Integrity says how much each table takes up.</p>

                    <h4>What it asks ESI</h4>
                    <p>Pages never call anything. Everything is read from what SeAT already pulls: wallet journals, killmails, affiliations, corporation histories and member tracking. Four things are asked of ESI directly, all public and with no token:</p>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><code>/characters/affiliation/</code></td><td>Where a ratter is now, for one SeAT's member tracking does not list: one request per 1000 characters, once a day. A director token with <code>esi-corporations.track_members.v1</code> means none at all for the members it lists.</td></tr>
                            <tr><td><code>/universe/names/</code></td><td>A name nobody has on file, only for those.</td></tr>
                            <tr><td><code>/characters/{id}/corporationhistory/</code></td><td>When a ratter left, where SeAT's copy does not show it yet: once per move.</td></tr>
                            <tr><td><code>/universe/system_kills/</code></td><td>The hourly kill counts for the Outsiders alert, and nothing without a home space set.</td></tr>
                        </tbody>
                    </table>
                    <p>It stops calling ESI while the error budget it shares with SeAT is low, and EVEWho and zKillboard answer instead.</p>
                </div>
            </div>

            {{-- Permissions --}}
            <div id="permissions" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-user-shield"></i> Permissions</h3>
                    <p>Each tier includes the ones below it, so a director does not also need Member.</p>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td><code>ratting-manager.view</code></td><td>This Help &amp; Documentation page. The Ratting Manager menu shows with this page alone.</td></tr>
                            <tr><td><code>ratting-manager.member</code></td><td>My Ratting, and the leaderboard with rank and hours.</td></tr>
                            <tr><td><code>ratting-manager.director</code></td><td>For the tracked corporations their characters are in now: the Corporation tab, the Monthly Report, the full leaderboard with everyone's ISK and its CSV downloads, and the Ratter Lookup.</td></tr>
                            <tr><td><code>ratting-manager.admin</code></td><td>All of that for every tracked corporation, plus Settings and Diagnostics.</td></tr>
                        </tbody>
                    </table>
                    <p>This is checked on the server for every page and download, so a link cannot reach another corporation's figures. A director with no character in a tracked corporation gets the member pages. A role alone decides who gets in, unless <strong>Close the pages to people who have left</strong> is switched on under Settings.</p>
                </div>
            </div>

            {{-- Custom Styling --}}
            <div id="custom-styling" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-paint-brush"></i> CSS Overrides Guide</h3>
                    <p>Ratting Manager wraps every page in CSS hook classes, so you can restyle any part of it from SeAT's custom CSS or your own theme stylesheet, without editing the plugin's files, which are overwritten on every update.</p>

                    <h4>CSS Class Hierarchy</h4>
                    <p>Every page carries one or both of the two wrapper classes, so to style all of Ratting Manager, target both.</p>
                    <ul>
                        <li><code>.ratting-manager-dashboard</code>: the Dashboard, Leaderboard, Ratter Lookup, a player's page, the full lists, Settings and this page.</li>
                        <li><code>.ratting-manager-wrapper</code>: Settings, Diagnostics and this page.</li>
                        <li><code>.diagnostic-page</code>: beside the wrapper on Diagnostics only. <code>.help-page</code>: beside both on this page only.</li>
                    </ul>

                    <h4>Reusable Component Classes</h4>
                    <p>Most of the plugin is built from a few shared classes. Target one to restyle a widget everywhere it appears:</p>
                    <ul>
                        <li><code>.card-dark</code>: the dark card chrome (header and body) used on every page.</li>
                        <li><code>.card-title</code> and <code>.card-tools</code>: a card's heading, and the pickers and buttons on the right of its header.</li>
                        <li><code>.info-box</code> and <code>.info-box-icon</code>: the coloured figure tiles on My Ratting.</li>
                        <li><code>.btn-rm-primary</code> and <code>.btn-rm-secondary</code>: the plugin's buttons.</li>
                        <li><code>.badge-coverage-full</code>, <code>.badge-coverage-partial</code> and <code>.badge-coverage-none</code>: the coverage badges.</li>
                        <li><code>.rm-estimated</code>: the dashed underline on figures worked out from the corporation's cut. <code>.rm-left-tag</code>: the tag beside a player who has left. <code>.rm-auth-tag</code>: the badge beside a player SeAT holds no token for. <code>.rm-subtabs</code>: the Corporation tab's sections.</li>
                    </ul>

                    <h4>Example Overrides</h4>
                    <h5>/* Tint the background of every Ratting Manager page */</h5>
                    <pre><code>.ratting-manager-dashboard, .ratting-manager-wrapper { background-color: #0d0d12; }</code></pre>
                    <h5>/* Make worked-out figures stand out more */</h5>
                    <pre><code>.ratting-manager-dashboard .rm-estimated { border-bottom-color: #fbbf24; }</code></pre>
                    <h5>/* Resize the icons in the figure tiles (handy on a custom SeAT theme) */</h5>
                    <pre><code>.ratting-manager-dashboard .info-box .info-box-icon i { font-size: 2.25rem; }</code></pre>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Where to Add Custom CSS:</strong>
                            SeAT loads two custom stylesheets if they exist: <code>custom-layout.css</code>, for every page, and <code>custom-layout-mini.css</code>, for the sign-in page only. On a bare-metal install, put <code>custom-layout.css</code> in SeAT's <code>public/</code> directory (for example <code>/var/www/seat/public/custom-layout.css</code>). On SeAT Docker, put it in <code>/opt/seat-docker/custom/</code>, mount it to <code>/var/www/seat/public/</code> in <code>docker-compose.override.yml</code>, and bring the stack back up. There is no SeAT setting to switch on. Full guide: <a href="https://eveseat.github.io/docs/styling/" target="_blank" rel="noopener">SeAT styling docs</a>.
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-lightbulb"></i>
                        Ratting Manager's own stylesheet is written for the standard SeAT layout. If you run a custom SeAT theme, small tweaks belong in your theme's CSS, so the plugin and your theme each survive updates on their own.
                    </div>
                </div>
            </div>

            {{-- FAQ --}}
            <div id="faq" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
                    @foreach([
                        'Why is some of my income marked as worked out?' => 'Those ticks are only in the corporation journal, usually because your wallet token is missing, new or lost. Adding the token brings in the last 30 days of your journal, and the next import pairs them, within about half an hour of SeAT pulling them.',
                        'Why is my ISK per hour lower in a fleet?' => 'Fleets share bounties, so each member receives part of each payout. Your ISK per hour is what you received.',
                        'Why can members not see anyone\'s ISK on the leaderboard?' => 'Members see rank and hours only, so nobody is ranked on what they earn in front of the corporation. Each member sees their own ISK on My Ratting, and directors see everyone\'s.',
                        'What does the Ex-member tag mean?' => 'None of the player\'s characters is in the corporations shown now, and it gives the day they left. Ratting counts for the corporation a character was in at the time, so they stay on the boards for the periods they ratted; the tag says why someone you no longer see in the corporation is listed.',
                        'What does Not authed with SeAT mean?' => 'SeAT holds no live token for any of the player\'s characters: they were never added, or have lost every token. Hover the badge to see which. They still count, from the corporation journal, and are watched for leaving like anyone else.',
                        'Someone left just after a tick. Is it lost?' => 'No. The tick\'s row carries the time it paid and names them, so it counts for the corporation that took the cut, even when it lands after they have gone.',
                        'How long is everything kept?' => 'See Data and ESI. Raw rows for as long as Keep raw ticks says, the daily summaries behind the charts for 365 days, and monthly totals for 25 months.',
                        'What is the difference between ISK ratted and income?' => 'Income is what the pilots received. ISK ratted is what the rats paid before tax, so income plus the corporation\'s cut.',
                        'A member lost their token. Have we lost their ratting?' => 'Not if it comes back within 30 days: SeAT brings the journal back and the next import fills it in. See When a Token Is Lost.',
                        'Why can I not see another corporation as a director?' => 'Directors read the tracked corporations their characters are in now. Admins read all of them.',
                        'Why does the Outsiders alert arrive hours late?' => 'It compares ESI\'s count for an hour with what the tracked corporations killed in it, and their ticks take one to two hours to reach SeAT. An hour is looked at once it is three hours old, so members\' own ratting is never mistaken for outsiders.',
                        'Why does the first import only go back a year?' => 'No page shows more than the last 12 months, so <code>ratting-manager:initialize</code> imports at most the last 365 days SeAT holds.',
                        'Why does the change column show a dash?' => 'There is nothing to compare with: the period before reaches back further than the imported ratting, or the player did not rat in it.',
                        'A rat shows as Other or Size not known.' => 'It is a type the plugin could not place. It still counts in every total. Diagnostics, Master Test lists it.',
                        'Will the CSV open in Excel?' => 'Yes. It is UTF-8 with a byte order mark, so names with accents show right, and ISK is written as plain numbers. Any text starting with <code>=</code>, <code>+</code>, <code>-</code> or <code>@</code> gets a quote in front, so a spreadsheet never runs it as a formula.',
                    ] as $question => $answer)
                        <div class="faq-item">
                            <div class="faq-question">
                                <strong>{{ $question }}</strong>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                <p>{!! $answer !!}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Troubleshooting --}}
            <div id="troubleshooting" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-wrench"></i> Troubleshooting Guide</h3>
                    <p>The problems that come up most, and what to check. Most of the checks are on the Diagnostics page, which admins open from Settings.</p>

                    <h4>Common Issues</h4>

                    <h5>A member's ratting does not show up</h5>
                    <p>Their ticks are not reaching the plugin, or not from a tracked corporation.</p>
                    <ul>
                        <li>The character has to be in a tracked corporation at the time of the tick.</li>
                        <li>With a wallet token on SeAT, ticks come from their own journal; without one, from the corporation journal, which needs a director token with wallet access.</li>
                        <li>Allow one to two hours from the tick.</li>
                        <li>Diagnostics, Tick Trace follows one journal row through the plugin and says what happened to it.</li>
                    </ul>

                    <h5>A director has no Corporation tab, or a page says there is no corporation to read</h5>
                    <p>Directors read the tracked corporations their characters are in now, and SeAT has none of theirs in one.</p>
                    <ul>
                        <li>Their character has to be on SeAT, on their account, and in a tracked corporation.</li>
                        <li>Someone who has to read every tracked corporation needs the Admin permission.</li>
                    </ul>

                    <h5>A member's ratting has gone quiet after a token problem</h5>
                    <p>See When a Token Is Lost. Wallet tokens lost on the Corporation tab shows who and until when.</p>

                    <h5>A ratter stays as Character info in progress</h5>
                    <p>SeAT does not know them and the background lookup has not answered yet.</p>
                    <ul>
                        <li>Diagnostics, Health Checks shows whether the lookup schedules are running.</li>
                        <li>The lookup stops calling ESI while the error budget it shares with SeAT is low, and falls back to EVEWho and zKillboard.</li>
                        <li><code>ratting-manager:resolve-characters --stats</code> shows what the lookup holds and what is waiting.</li>
                    </ul>

                    <h5>An alert I expected was not sent</h5>
                    <p>It is switched off, bound to no webhook that takes that corporation, or has already been sent.</p>
                    <ul>
                        <li>The Routing Map shows where each notification goes now.</li>
                        <li>The notification has to be switched on and ticked for a webhook that is switched on and covers the corporation.</li>
                        <li>The Outsiders alert also needs a home space.</li>
                        <li>Diagnostics, Notification Testing shows what each alert has already sent for.</li>
                    </ul>

                    <h5>A webhook stopped posting</h5>
                    <p>Discord or Slack is refusing the posts.</p>
                    <ul>
                        <li>Diagnostics, Notification Testing shows every send with the answer it got, and each webhook's delivery health.</li>
                        <li>A deleted Discord webhook answers 404: make a new one and paste its URL with the webhook's edit button.</li>
                        <li>URLs are encrypted with SeAT's <code>APP_KEY</code>. If the key changed, or the database came from another server, Settings says the URL has to be entered again, and nothing is sent until it is.</li>
                    </ul>

                    <h5>The figures have not changed for hours</h5>
                    <p>The import has stopped, or SeAT has stopped pulling the journals.</p>
                    <ul>
                        <li>Diagnostics, Health Checks shows when the import last ran; Master Test shows whether each corporation's journal is coming in.</li>
                        <li>The Data stalled alert posts when a journal or the import stops, if it is bound to a webhook.</li>
                        <li><code>ratting-manager:process</code> runs the import by hand.</li>
                    </ul>

                    <div class="info-box">
                        <i class="fas fa-life-ring"></i>
                        <div>
                            <strong>Need More Help?</strong>
                            If something is not covered here, open an issue on the <a href="https://github.com/MattFalahe/Ratting-Manager/issues" target="_blank" rel="noopener">GitHub repository</a> with what you expected, what you saw, your SeAT version, and what Diagnostics shows.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Admin Diagnostics --}}
            <div id="admin-diagnostics" class="help-section">
                <div class="help-card">
                    <h3><i class="fas fa-stethoscope"></i> Admin Diagnostics</h3>
                    <p>Admins have a Diagnostics page for checking the plugin's health and testing notifications. Nothing on it changes the plugin's data; the one thing it sends is the sample posts from Notification Testing.</p>

                    <h4>Where to find it</h4>
                    <p>Settings has an <strong>Open Diagnostics</strong> button; the page is at <code>/ratting-manager/diagnostic</code>. It is not in the menu, and only admins can open it.</p>

                    <h4>What each tab does</h4>
                    <table class="table table-sm">
                        <tbody>
                            @foreach([
                                'tab_health' => ['health_what', 'health_when'],
                                'tab_master_test' => ['master_what', 'master_when'],
                                'tab_settings_health' => ['settings_what', 'settings_when'],
                                'tab_data_integrity' => ['integrity_what', 'integrity_when'],
                                'tab_tick_trace' => ['trace_what', 'trace_when'],
                                'tab_notifications' => ['notif_what', 'notif_when'],
                            ] as $tab => [$what, $when])
                                <tr>
                                    <td><strong>{{ trans('ratting-manager::diagnostic.' . $tab) }}</strong></td>
                                    <td>{{ trans('ratting-manager::diagnostic.' . $what) }} <em>{{ trans('ratting-manager::diagnostic.' . $when) }}</em></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('javascript')
<script>
$(function () {
    $('.help-nav .nav-link').on('click', function (e) {
        e.preventDefault();
        var section = $(this).data('section');
        $('.help-nav .nav-link').removeClass('active');
        $(this).addClass('active');
        $('.help-section').removeClass('active');
        $('#' + section).addClass('active');
        window.location.hash = section;

        // Picking a section ends a search.
        $('#helpSearch').val('');
        $('.help-content').removeClass('help-searching');
        $('.help-card, .whats-new-box').show();
    });

    if (window.location.hash) {
        $('.help-nav .nav-link[data-section="' + window.location.hash.substring(1) + '"]').click();
    }

    $('.faq-question').on('click', function () {
        $(this).closest('.faq-item').toggleClass('open');
    });

    // Every section is searched, not only the open one.
    var searchTimeout;
    $('#helpSearch').on('input', function () {
        clearTimeout(searchTimeout);
        var query = $(this).val().toLowerCase();

        searchTimeout = setTimeout(function () {
            if (query.length < 2) {
                $('.help-content').removeClass('help-searching');
                $('.help-card, .whats-new-box').show();
                return;
            }

            $('.help-content').addClass('help-searching');
            $('.help-card, .whats-new-box').each(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(query) !== -1);
            });
        }, 300);
    });
});
</script>
@endpush
