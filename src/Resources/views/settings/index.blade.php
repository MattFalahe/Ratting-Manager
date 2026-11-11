@extends('web::layouts.grids.12')

@section('title', trans('ratting-manager::settings.title'))
@section('page_header', trans('ratting-manager::settings.title'))

@push('head')
    @include('ratting-manager::partials.head')
@endpush

@section('full')
@php
    // Sections by what they are for, in the order someone setting up the plugin needs them.
    $groups = [
        ['fas fa-crosshairs', trans('ratting-manager::notification_settings.group_tracking'), [
            ['tracking', 'fas fa-building', trans('ratting-manager::notification_settings.section_tracking')],
        ]],
        ['fas fa-bell', trans('ratting-manager::notification_settings.group_alerts'), [
            ['webhooks', 'fas fa-satellite-dish', trans('ratting-manager::notification_settings.section_webhooks')],
            ['notifications', 'fas fa-bell', trans('ratting-manager::notification_settings.section_notifications')],
            ['routing-map', 'fas fa-project-diagram', trans('ratting-manager::notification_settings.section_routing')],
        ]],
    ];
@endphp
<div class="ratting-manager-wrapper ratting-manager-dashboard">
    @if(session('success'))
        <div class="alert alert-success"><i class="fas fa-check"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> {{ session('error') }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-3">
            <div class="card card-dark">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cog"></i> {{ trans('ratting-manager::notification_settings.menu') }}</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-pills flex-column rm-settings-nav">
                        @foreach($groups as [$icon, $title, $sections])
                            <li class="nav-header"><i class="{{ $icon }}"></i> {{ $title }}</li>
                            @foreach($sections as [$id, $sectionIcon, $label])
                                <li class="nav-item">
                                    <a href="#{{ $id }}" class="nav-link {{ $id === 'tracking' ? 'active' : '' }}" data-section="{{ $id }}">
                                        <i class="{{ $sectionIcon }}"></i> {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            </div>

            @include('ratting-manager::settings.partials.status')
        </div>

        <div class="col-lg-9">
            <div class="rm-settings-section active" id="rm-section-tracking">
                @include('ratting-manager::settings.sections.tracking')
            </div>
            <div class="rm-settings-section" id="rm-section-webhooks">
                @include('ratting-manager::settings.sections.webhooks')
            </div>
            <div class="rm-settings-section" id="rm-section-notifications">
                @include('ratting-manager::settings.sections.notifications')
            </div>
            <div class="rm-settings-section" id="rm-section-routing-map">
                @include('ratting-manager::settings.sections.routing-map')
            </div>
        </div>
    </div>
</div>

@include('ratting-manager::settings.partials.webhook-modal')
@endsection

@push('javascript')
<script src="{{ \RattingManager\Support\Asset::url('js/eve-time.js') }}"></script>
<script>
(function ($) {
    // Sections switch in place and the address keeps the one open, so a
    // save that comes back to #notifications lands on Notifications.
    function show(section) {
        if (!$('#rm-section-' + section).length) { section = 'tracking'; }
        $('.rm-settings-nav .nav-link').removeClass('active').filter('[data-section="' + section + '"]').addClass('active');
        $('.rm-settings-section').removeClass('active');
        $('#rm-section-' + section).addClass('active');
    }
    $('.rm-settings-nav .nav-link').on('click', function (e) {
        e.preventDefault();
        var section = $(this).data('section');
        show(section);
        history.replaceState(null, '', '#' + section);
    });
    $(document).on('click', '.rm-section-link', function (e) {
        e.preventDefault();
        show($(this).data('section'));
        history.replaceState(null, '', '#' + $(this).data('section'));
    });
    if (location.hash) { show(location.hash.substring(1)); }
    $(window).on('hashchange', function () { show(location.hash.substring(1)); });

    // A stored mention shown by name, from the Discord role lists SeAT has.
    var roleData = JSON.parse(document.getElementById('rm-role-data').textContent || '{}');
    var text = roleData.text || {};
    function pill(value) {
        value = $.trim(value || '');
        if (value === '') { return ''; }
        if (value.toLowerCase() === 'none') {
            return '<span class="rm-role-pill is-silenced"><i class="fas fa-bell-slash"></i> ' + $('<i>').text(text.no_ping).html() + '</span>';
        }
        var role = value.match(/^<@&(\d+)>$/) || value.match(/^(\d+)$/);
        if (role) {
            var known = (roleData.roles || {})[role[1]];
            if (known) {
                var dot = known.color && /^#[0-9a-f]{6}$/i.test(known.color) ? '<span class="rm-role-dot" style="background:' + known.color + '"></span>' : '';
                return '<span class="rm-role-pill">' + dot + '@' + $('<i>').text(known.name).html() + '</span>';
            }
            return roleData.available ? '<span class="rm-role-pill is-unknown"><i class="fas fa-question-circle"></i> ' + $('<i>').text(text.unknown_role.replace(':id', role[1])).html() + '</span>' : '';
        }
        if (/^<@!?\d+>$/.test(value)) {
            return '<span class="rm-role-pill is-user"><i class="fas fa-user"></i> ' + $('<i>').text(text.user).html() + '</span>';
        }
        return '<span class="rm-role-pill is-unknown"><i class="fas fa-exclamation-triangle"></i> ' + $('<i>').text(text.invalid).html() + '</span>';
    }
    function refresh($input) { $input.closest('.rm-role-field').find('.rm-role-name').html(pill($input.val())); }
    $('.rm-role-input').each(function () { refresh($(this)); }).on('input change', function () { refresh($(this)); });
    $(document).on('click', '.rm-no-ping', function () {
        var $input = $(this).closest('.rm-role-field').find('.rm-role-input');
        $input.val('none');
        refresh($input);
    });

    // Switching a type off dims its body, not its title.
    $('.rm-type-toggle').on('change', function () {
        $(this).closest('.rm-notif-type').attr('data-enabled', this.checked ? '1' : '0');
    });

    // One modal for adding and editing, moved to <body> so the page's
    // stacking never covers it.
    var $modal = $('#rm-webhook-modal').appendTo('body');
    $('.rm-webhook-add').on('click', function () {
        var $f = $modal.find('form');
        $f.attr('action', $f.data('store-url'));
        $modal.find('.rm-modal-title-add').show();
        $modal.find('.rm-modal-title-edit').hide();
        $f.find('[name=description]').val('');
        var $url = $f.find('[name=webhook_url]');
        $url.val('').prop('required', true).attr('placeholder', $url.data('placeholder'));
        $f.find('.rm-wh-url-help').show();
        $f.find('.rm-wh-url-keep').hide();
        $f.find('[name=corporation_id]').val('');
        $f.find('[name=enabled]').prop('checked', true);
        $f.find('.rm-wh-type').each(function () { this.checked = $(this).data('suggested') === 1; });
        $modal.modal('show');
    });
    $('.rm-webhook-edit').on('click', function () {
        var $b = $(this), $f = $modal.find('form');
        // Read as text: jQuery's data() would turn some values into numbers.
        var types = JSON.parse($b.attr('data-types') || '[]');
        $f.attr('action', $b.data('url'));
        $modal.find('.rm-modal-title-add').hide();
        $modal.find('.rm-modal-title-edit').show();
        $f.find('[name=description]').val($b.attr('data-description'));
        // The URL stays on the server: only its cut form is shown, and empty keeps it.
        var lost = $b.attr('data-url-lost') === '1', $url = $f.find('[name=webhook_url]');
        $url.val('').prop('required', lost).attr('placeholder', lost ? $url.data('placeholder') : $b.attr('data-masked-url'));
        $f.find('.rm-wh-url-help').toggle(lost);
        $f.find('.rm-wh-url-keep').toggle(!lost);
        $f.find('[name=corporation_id]').val($b.data('corporation') || '');
        $f.find('[name=enabled]').prop('checked', !!$b.data('enabled'));
        $f.find('.rm-wh-type').each(function () { this.checked = types.indexOf(this.value) !== -1; });
        $modal.modal('show');
    });
    $('.rm-confirm').on('submit', function (e) {
        if (!confirm($(this).data('confirm'))) { e.preventDefault(); }
    });

    // Corporation picker: type a name or ticker, or open the whole list, then
    // pick one to load its details and confirm tracking it.
    (function () {
        var input = document.getElementById('rm-corp-search');
        if (!input) { return; }
        var list = document.getElementById('rm-corp-options');
        var preview = document.getElementById('rm-corp-preview');
        var text = document.getElementById('rm-corp-text').dataset;
        var all = JSON.parse(document.getElementById('rm-corp-data').textContent || '[]');
        var shown = [];
        var active = -1;

        function score(c, q) {
            var ticker = c.ticker.toLowerCase(), name = c.name.toLowerCase();
            if (ticker === q) { return 0; }
            if (ticker.indexOf(q) === 0) { return 1; }
            if (name.indexOf(q) === 0) { return 2; }
            if ((' ' + name).indexOf(' ' + q) !== -1) { return 3; }
            if (name.indexOf(q) !== -1 || ticker.indexOf(q) !== -1) { return 4; }
            return -1;
        }

        function find(query) {
            var q = query.trim().toLowerCase();
            return all
                .map(function (c) { return { c: c, s: q ? score(c, q) : 0 }; })
                .filter(function (m) { return m.s >= 0; })
                .sort(function (a, b) { return a.s - b.s || b.c.on_seat - a.c.on_seat || a.c.name.localeCompare(b.c.name); })
                .slice(0, 100)
                .map(function (m) { return m.c; });
        }

        // The matched part of a name in bold, built as text so a name can never be read as markup.
        function highlight(el, value, q) {
            var i = q ? value.toLowerCase().indexOf(q) : -1;
            if (i < 0) { el.appendChild(document.createTextNode(value)); return; }
            el.appendChild(document.createTextNode(value.slice(0, i)));
            var mark = document.createElement('mark');
            mark.textContent = value.slice(i, i + q.length);
            el.appendChild(mark);
            el.appendChild(document.createTextNode(value.slice(i + q.length)));
        }

        function open() {
            var q = input.value.trim().toLowerCase();
            shown = find(input.value);
            active = shown.length ? 0 : -1;
            list.innerHTML = '';
            if (!shown.length) {
                var empty = document.createElement('li');
                empty.className = 'rm-typeahead-empty';
                empty.textContent = text.none;
                list.appendChild(empty);
            }
            shown.forEach(function (c, i) {
                var li = document.createElement('li');
                li.id = 'rm-corp-option-' + i;
                li.setAttribute('role', 'option');
                li.dataset.index = i;
                highlight(li, '[' + c.ticker + '] ', q);
                highlight(li, c.name, q);
                var meta = [];
                if (c.alliance) { meta.push('<' + c.alliance + '>'); }
                if (c.on_seat) { meta.push(text.onSeat.replace(':count', c.on_seat)); }
                if (meta.length) {
                    var small = document.createElement('small');
                    small.textContent = meta.join(' \u00b7 ');
                    li.appendChild(small);
                }
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            mark();
        }

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function mark() {
            $(list).children().removeClass('active');
            if (active < 0) { input.removeAttribute('aria-activedescendant'); return; }
            var li = document.getElementById('rm-corp-option-' + active);
            li.classList.add('active');
            li.scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', li.id);
        }

        function pick(c) {
            input.value = '[' + c.ticker + '] ' + c.name;
            close();
            preview.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin"></i> ' + $('<i>').text(text.loading).html() + '</div>';
            $.get(text.url.replace(/\/0$/, '/' + c.id))
                .done(function (html) { preview.innerHTML = html; })
                .fail(function () { preview.innerHTML = '<div class="text-danger">' + $('<i>').text(text.failed).html() + '</div>'; });
        }

        input.addEventListener('input', open);
        input.addEventListener('focus', open);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (list.hidden) { open(); return; }
                if (!shown.length) { return; }
                active = (active + (e.key === 'ArrowDown' ? 1 : shown.length - 1)) % shown.length;
                mark();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (!list.hidden && active >= 0) { pick(shown[active]); }
            } else if (e.key === 'Escape') {
                close();
            }
        });
        list.addEventListener('mousedown', function (e) {
            var li = e.target.closest('li[data-index]');
            if (li) { e.preventDefault(); pick(shown[+li.dataset.index]); }
        });
        document.querySelector('.rm-typeahead-toggle').addEventListener('click', function () {
            if (list.hidden) { input.value = ''; input.focus(); open(); } else { close(); }
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.rm-typeahead')) { close(); }
        });
        preview.addEventListener('click', function (e) {
            if (e.target.closest('.rm-corp-cancel')) {
                preview.innerHTML = '';
                input.value = '';
                input.focus();
            }
        });
    })();
})(jQuery);
</script>
@endpush
