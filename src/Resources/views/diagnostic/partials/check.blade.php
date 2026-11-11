<div class="diag-section">
    <div class="diag-section-header">
        <h3 class="diag-section-title">{{ $check['label'] }}</h3>
        <span class="diag-badge {{ $check['status'] }}">{{ strtoupper($check['status']) }}</span>
    </div>
    <div class="diag-section-body">
        <p class="diag-msg">{{ $check['message'] }}</p>
        @if(!empty($check['details']))
            <dl class="diag-kv" style="margin-top:0.8rem;">
                @foreach($check['details'] as $k => $v)
                    <dt>{{ $k }}</dt>
                    <dd>{{ is_bool($v) ? ($v ? 'true' : 'false') : (string) $v }}</dd>
                @endforeach
            </dl>
        @endif
    </div>
</div>
