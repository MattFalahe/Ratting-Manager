{{-- Weekday by hour in EVE time. Each cell carries its count in the tooltip, so the colour never has to be read on its own. --}}
@php
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $cellKey = $perPlayer ? 'ratting-manager::dashboard.heatmap_cell_corp' : 'ratting-manager::dashboard.heatmap_cell_personal';
@endphp
<div class="rm-heatmap" role="table" aria-label="{{ $title ?? '' }}">
    <div></div>
    @for($h = 0; $h < 24; $h++)
        <div class="text-center" role="columnheader">{{ $h % 3 === 0 ? str_pad($h, 2, '0', STR_PAD_LEFT) : '' }}</div>
    @endfor
    @foreach($days as $d => $dayName)
        <div class="rm-heat-label" role="rowheader">{{ $dayName }}</div>
        @for($h = 0; $h < 24; $h++)
            @php $value = $heatmap['values'][$d][$h]; @endphp
            <div class="rm-heat-cell rm-heat-{{ $heatmap['levels'][$d][$h] }}" role="cell"
                 title="{{ trans($cellKey, ['day' => $dayName, 'hour' => str_pad($h, 2, '0', STR_PAD_LEFT), 'value' => $value]) }}"></div>
        @endfor
    @endforeach
</div>
<div class="rm-heatmap-legend">
    {{ trans('ratting-manager::dashboard.heatmap_less') }}
    @for($l = 0; $l <= 5; $l++)
        <span class="rm-heat-cell rm-heat-{{ $l }}" style="background-color: var(--rm-heat-{{ $l }});"></span>
    @endfor
    {{ trans('ratting-manager::dashboard.heatmap_more') }}
    <span class="ml-auto">{{ trans('ratting-manager::common.eve_time') }}</span>
</div>
