{{-- "View all N" for a list cut short on a dashboard, linking to the page with all of it. Shown only when rows were left out. --}}
@if(!empty($url) && $limit !== null && $count > $limit)
    <a href="{{ $url }}" class="btn btn-xs btn-rm-secondary rm-view-all">
        {{ trans('ratting-manager::lists.view_all', ['count' => number_format($count)]) }} <i class="fas fa-arrow-right"></i>
    </a>
@endif
