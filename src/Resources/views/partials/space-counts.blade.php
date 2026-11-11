{{-- How much of New Eden a period's ratting covered. --}}
<p class="rm-space-counts">
    <i class="fas fa-map-marked-alt"></i>
    {{ trans('ratting-manager::dashboard.ratted_in', [
        'systems' => trans_choice('ratting-manager::dashboard.count_systems', $counts['systems'], ['count' => number_format($counts['systems'])]),
        'constellations' => trans_choice('ratting-manager::dashboard.count_constellations', $counts['constellations'], ['count' => number_format($counts['constellations'])]),
        'regions' => trans_choice('ratting-manager::dashboard.count_regions', $counts['regions'], ['count' => number_format($counts['regions'])]),
    ]) }}
</p>
