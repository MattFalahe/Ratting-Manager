@extends('ratting-manager::layouts.page')

@section('title', $title)
@section('page_header', $title)

@push('head')
    <link rel="stylesheet" href="{{ \RattingManager\Support\Asset::url('css/vendor/dataTables.bootstrap4.min.css') }}">
@endpush

@php
    // Filters worth offering: the period for lists that depend on it, and a
    // character or corporation when there is more than one to pick from.
    $filters = $byPeriod || count($characterOptions ?? []) > 1 || count($corporations ?? []) > 1;
@endphp
@section('rm-tabs')
    @include('ratting-manager::partials.page-tabs', ['icon' => 'fa-list', 'label' => $title])
@endsection

@section('rm-body')
    <p><a href="{{ $back }}"><i class="fas fa-arrow-left"></i> {{ $backLabel }}</a></p>

    <div class="card card-dark">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i> {{ $heading }}
                @if($byPeriod)
                    - {{ $period->label() }}
                @endif
            </h3>
            @if($filters)
                <div class="card-tools">
                    @include('ratting-manager::partials.period-picker', [
                        'showPeriod' => $byPeriod,
                        'characterOptions' => $characterOptions ?? [],
                        'pickedCharacter' => $pickedCharacter ?? null,
                        'corporations' => $corporations ?? [],
                        'pickedCorporation' => $pickedCorporation ?? null,
                    ])
                </div>
            @endif
        </div>
        @if($columnFilters !== [])
            {{-- Wired to the table below the page; each row carries the matching data-filter-{key}. --}}
            <div class="card-body rm-filters rm-list-filters">
                @foreach($columnFilters as $key => $filter)
                    <select class="form-control form-control-sm" data-filter="{{ $key }}" aria-label="{{ $filter['label'] }}" title="{{ $filter['label'] }}">
                        <option value="">{{ $filter['any'] }}</option>
                        @foreach($filter['options'] as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                @endforeach
                <button type="button" class="btn btn-sm btn-rm-secondary rm-clear-filters" hidden>
                    <i class="fas fa-times"></i> {{ trans('ratting-manager::lists.clear_filters') }}
                </button>
            </div>
        @endif
    </div>

    @switch($list)
        @case('sessions')
            @include('ratting-manager::dashboard.partials.sessions-table', ['rows' => $rows, 'tableId' => 'rm-list-table'])
            @break
        @case('systems')
            @include('ratting-manager::partials.systems-table', ['rows' => $rows, 'corporation' => $corporation, 'tableId' => 'rm-list-table'])
            @break
        @case('spawns')
            @include('ratting-manager::dashboard.partials.spawns', ['spawns' => $rows, 'showCharacter' => true, 'tableId' => 'rm-list-table'])
            @break
        @case('untracked')
            @include('ratting-manager::dashboard.partials.untracked', ['rows' => $rows, 'tableId' => 'rm-list-table'])
            @break
        @case('gone-quiet')
            @include('ratting-manager::dashboard.partials.gone-quiet', ['rows' => $rows, 'tableId' => 'rm-list-table'])
            @break
    @endswitch
@endsection

@section('rm-after')
    @include('ratting-manager::partials.character-lookup')
@endsection


@push('javascript')
@include('ratting-manager::partials.datatables')
<script src="{{ \RattingManager\Support\Asset::url('js/eve-time.js') }}"></script>
<script>
$(function () {
    // Searchable and sortable, in the order the server gave. An empty list
    // has one "nothing here" row spanning the columns, which DataTables
    // cannot take, so it is left as it is.
    var $table = $('#rm-list-table');
    if ($.fn.DataTable && $table.length && !$table.find('tbody td[colspan]').length) {
        var table = $table.DataTable({ order: [], pageLength: 25, lengthMenu: [25, 50, 100, 250] });

        // The dropdowns keep a row when its data-filter-{key} matches every
        // one picked, on top of the search box.
        var $filters = $('.rm-list-filters select[data-filter]');
        var $clear = $('.rm-list-filters .rm-clear-filters');
        if ($filters.length) {
            $.fn.dataTable.ext.search.push(function (settings, data, index) {
                if (settings.nTable !== $table[0]) {
                    return true;
                }
                var row = settings.aoData[index].nTr;
                var keep = true;
                $filters.each(function () {
                    if (this.value !== '' && row.getAttribute('data-filter-' + this.getAttribute('data-filter')) !== this.value) {
                        keep = false;
                    }
                });
                return keep;
            });
            $filters.on('change', function () {
                $clear.prop('hidden', !$filters.filter(function () { return this.value !== ''; }).length);
                table.draw();
            });
            $clear.on('click', function () {
                $filters.val('');
                $clear.prop('hidden', true);
                table.draw();
            });
        }
    }
});
</script>
@endpush
