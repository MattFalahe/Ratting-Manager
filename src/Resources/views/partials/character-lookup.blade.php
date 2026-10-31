{{--
    Characters this page could not name yet are being looked up in the
    background, so the page shows them as in progress instead of waiting on
    ESI. Say so, and refresh once they are in, unless the reader is busy in a
    form, in which case just tell them. The Corporation tab, which loads after
    the page, hands its own over through window.rmCharacterLookup.watch().
    Included last on a page, after everything that names characters.
--}}
@php
    $pendingCharacters = app(\RattingManager\Services\Character\Players::class)->shownPending();
@endphp
@push('javascript')
<script>
(function () {
    'use strict';

    if (window.rmCharacterLookup) {
        window.rmCharacterLookup.watch(@json(array_values($pendingCharacters)));
        return;
    }

    var url = @json(route('ratting-manager.characters.pending'));
    var pendingText = @json(trans('ratting-manager::common.characters_pending_notice', ['count' => '__COUNT__']));
    var readyText = @json(trans('ratting-manager::common.characters_ready_notice'));
    var ids = [];
    var checks = 0;
    var started = false;

    function notice(text, kind) {
        var root = document.querySelector('.ratting-manager-dashboard, .ratting-manager-wrapper');
        if (!root) {
            return;
        }
        var box = document.getElementById('rm-lookup-notice');
        if (!box) {
            box = document.createElement('div');
            box.id = 'rm-lookup-notice';
            root.insertBefore(box, root.firstChild);
        }
        box.className = 'alert alert-' + kind;
        box.innerHTML = '';
        var icon = document.createElement('i');
        icon.className = kind === 'info' ? 'fas fa-spinner fa-spin mr-2' : 'fas fa-check mr-2';
        box.appendChild(icon);
        box.appendChild(document.createTextNode(text));
    }

    function check() {
        checks++;
        $.getJSON(url, { ids: ids.slice(0, 500).join(',') }).done(function (data) {
            if (data && Array.isArray(data.pending) && data.pending.length === 0) {
                var field = document.activeElement;
                if (field && /^(INPUT|TEXTAREA|SELECT)$/.test(field.tagName)) {
                    notice(readyText, 'success');
                    return;
                }
                window.location.reload();
                return;
            }
            if (checks < 20) {
                setTimeout(check, 15000);
            }
        }).fail(function () {
            if (checks < 20) {
                setTimeout(check, 30000);
            }
        });
    }

    function watch(more) {
        (more || []).forEach(function (id) {
            id = parseInt(id, 10);
            if (id > 0 && ids.indexOf(id) === -1) {
                ids.push(id);
            }
        });

        if (!ids.length || started) {
            return;
        }

        started = true;
        notice(pendingText.replace('__COUNT__', ids.length), 'info');
        setTimeout(check, 10000);
    }

    window.rmCharacterLookup = { watch: watch };

    watch(@json(array_values($pendingCharacters)));
})();
</script>
@endpush
