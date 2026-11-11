{{-- Beside one character who has left the corporations shown: what they ratted before that still counts. --}}
@if(!empty($left))
    @php
        $when = \Carbon\Carbon::parse($left['left_at']);
        $title = trans($left['left_exact'] ? 'ratting-manager::common.left_title_exact' : 'ratting-manager::common.left_title_seen', ['time' => $when->format('Y-m-d H:i')]);
        $text = trans($left['left_exact'] ? 'ratting-manager::common.character_left_on' : 'ratting-manager::common.character_left_by', ['date' => $when->format('j M Y')]);
    @endphp
    <span class="rm-left-tag rm-alt-left-tag" title="{{ $title }}">{{ $text }}</span>
@endif
