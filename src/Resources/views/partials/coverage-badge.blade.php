@if($coverage === null)
    <span class="text-muted">-</span>
@elseif($coverage >= 95)
    <span class="badge badge-coverage-full" title="{{ trans('ratting-manager::common.coverage_title', ['percent' => $coverage]) }}"><i class="fas fa-check"></i> {{ trans('ratting-manager::common.coverage_full') }}</span>
@elseif($coverage > 0)
    <span class="badge badge-coverage-partial" title="{{ trans('ratting-manager::common.coverage_title', ['percent' => $coverage]) }}"><i class="fas fa-adjust"></i> {{ $coverage }}%</span>
@else
    <span class="badge badge-coverage-none" title="{{ trans('ratting-manager::common.coverage_title', ['percent' => 0]) }}"><i class="fas fa-eye-slash"></i> {{ trans('ratting-manager::common.coverage_none') }}</span>
@endif
