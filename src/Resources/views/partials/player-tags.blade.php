{{-- Beside a player: that they have left, or some of their characters have, since ratting still counts where it was earned, and that SeAT holds no token for them. --}}
@if($row['left'] ?? false)
    @php
        $when = !empty($row['left_at']) ? \Carbon\Carbon::parse($row['left_at']) : null;
        $exact = $row['left_exact'] ?? false;
        $title = $when === null
            ? trans('ratting-manager::common.left_title')
            : trans($exact ? 'ratting-manager::common.left_title_exact' : 'ratting-manager::common.left_title_seen', ['time' => $when->format('Y-m-d H:i')]);
        if (!empty($row['left_last_tick'])) {
            $title .= ' ' . trans('ratting-manager::common.left_last_tick', ['time' => substr($row['left_last_tick'], 0, 16)]);
        }
        $text = $when === null
            ? trans('ratting-manager::common.left')
            : trans($exact ? 'ratting-manager::common.left_on' : 'ratting-manager::common.left_by', ['date' => $when->format('j M Y')]);
    @endphp
    <span class="rm-left-tag" title="{{ $title }}">{{ $text }}</span>
@elseif(!empty($row['alts_left']))
    @php
        $title = collect($row['alts_left'])->map(fn ($alt) => trans(
            $alt['left_exact'] ? 'ratting-manager::common.alt_left_line' : 'ratting-manager::common.alt_gone_line',
            ['name' => $alt['name'], 'time' => \Carbon\Carbon::parse($alt['left_at'])->format('Y-m-d H:i')]
        ))->push(trans('ratting-manager::common.alts_left_title'))->implode(' ');
    @endphp
    <span class="rm-left-tag rm-alt-left-tag" title="{{ $title }}">{{ trans_choice('ratting-manager::common.alts_left', count($row['alts_left'])) }}</span>
@endif
@if(!empty($row['seat_auth']))
    <span class="rm-auth-tag" title="{{ trans('ratting-manager::common.not_authed_' . $row['seat_auth']) }}">{{ trans('ratting-manager::common.not_authed') }}</span>
@endif
