<link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/ratting-manager.css') }}">
<style>
/* Forced inline as Mining Manager does: some SeAT skins lighten cards and
   tables after the stylesheet loads, and the plugin's light text all but
   vanishes on them. */
.ratting-manager-dashboard .tab-content {
    background-color: #0f1115 !important;
}

.ratting-manager-dashboard .card-body,
.ratting-manager-dashboard .card-dark .card-body,
.ratting-manager-dashboard .card.card-dark .card-body,
.ratting-manager-dashboard div.card-body {
    background-color: #161922 !important;
    color: #e8e8e8 !important;
}

.ratting-manager-dashboard .card.card-dark,
.ratting-manager-dashboard .card-dark {
    background-color: #161922 !important;
    border-color: #2c3138 !important;
}

.ratting-manager-dashboard .card-dark .card-header,
.ratting-manager-dashboard .card.card-dark .card-header {
    background-color: #1a1d24 !important;
    border-bottom: 1px solid #2c3138 !important;
}

.ratting-manager-dashboard .table {
    color: #e8e8e8 !important;
}

.ratting-manager-dashboard .table thead th {
    background-color: #1a1d24 !important;
    color: #ffffff !important;
}

.ratting-manager-dashboard canvas {
    background-color: rgba(26, 29, 36, 0.5) !important;
}

.ratting-manager-dashboard .rm-chart {
    position: relative;
    height: 300px;
}

.ratting-manager-dashboard .rm-chart-tall {
    position: relative;
    height: 360px;
}

.ratting-manager-dashboard .rm-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.ratting-manager-dashboard .rm-filters select {
    width: auto;
    min-width: 150px;
}

.ratting-manager-dashboard .rm-period-range {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ratting-manager-dashboard .rm-period-range[hidden] {
    display: none !important;
}

.ratting-manager-dashboard .rm-period-range input[type="date"] {
    width: auto;
    color-scheme: dark;
}
</style>
<script>
// A pick in any of the plugin's filters applies straight away. The one
// exception is a custom range, which takes two dates, so it keeps its button:
// its dates show, and are sent, only while Custom range is picked. Listening
// on the document covers pickers a page loads later, such as the Corporation
// tab's, and requestSubmit() lets that form's own handler reload only the tab.
document.addEventListener('change', function (e) {
    var field = e.target;
    var form = field.form;
    if (!form || !form.classList.contains('rm-filters') || field.tagName !== 'SELECT') {
        return;
    }
    if (field.classList.contains('rm-period-select')) {
        var range = form.querySelector('.rm-period-range');
        var custom = field.value === 'custom';
        if (range) {
            range.hidden = !custom;
            Array.prototype.forEach.call(range.querySelectorAll('input'), function (input) {
                input.disabled = !custom;
            });
        }
        if (custom) {
            return;
        }
    }
    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
});
</script>
