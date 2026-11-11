{{-- Features that need another plugin get boxes of their own, so a missing
     plugin shows on the feature it takes away. Red: a crucial plugin is
     missing, or the connection to it is not built yet. Yellow: only an
     optional one is missing. --}}
@php
    $hub = $ecosystem['hub'];
    $roleNames = $ecosystem['features']['role-names'];
@endphp
<h4 class="eco-heading"><i class="fas fa-project-diagram"></i> With other plugins</h4>
<p>These need another plugin. <a href="#plugins" data-goto="plugins">Plugins &amp; Ecosystem</a> says what each one adds and whether this server has it.</p>
<div class="feature-grid">
    <div class="feature-item eco-box eco-hub eco-{{ $hub['state'] }}{{ $hub['state'] !== 'on' ? ' eco-pulse' : '' }}">
        @if($hub['banner'])
            <span class="eco-banner">{{ $hub['banner'] }}</span>
        @endif
        <i class="fas fa-network-wired"></i>
        <h5>Manager Core: The Ecosystem Era</h5>
        <p>Manager Core links the plugins: what happens in one reaches the others, one can ask another for what it knows, and they share prices instead of each fetching its own.</p>
        @if($hub['coming_soon'])
            <p class="eco-note">Ratting Manager does not connect to it yet. It works on its own today, and a later release brings it into the ecosystem.</p>
        @elseif(!$hub['installed'])
            <p class="eco-note">Manager Core is not installed on this server.</p>
        @endif
    </div>
    <div class="feature-item eco-box eco-{{ $roleNames['state'] }}{{ $roleNames['state'] !== 'on' ? ' eco-pulse' : '' }}">
        @if($roleNames['banner'])
            <span class="eco-banner">{{ $roleNames['banner'] }}</span>
        @endif
        <i class="fas fa-user-tag"></i>
        <h5>Roles picked by name</h5>
        <p>Settings lists your Discord roles to pick from for each notification and webhook, instead of a role ID typed by hand.</p>
        @if($roleNames['state'] === 'on')
            <span class="badge badge-success eco-tag">With {{ $roleNames['using'] }}</span>
        @else
            <p class="eco-note">Needs {{ $roleNames['needs'] }}. Until then, type a role as its ID.</p>
        @endif
    </div>
</div>
