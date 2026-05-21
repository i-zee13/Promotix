@props([
    'googleOAuthConnected' => false,
    'menuDomain' => null,
    'primaryConnection' => null,
])

@php
    $canDisconnect = (bool) $primaryConnection;
@endphp

<x-integrations.platform-card-dropdown label="Google platform options">
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'google-details' })">
        View Platform Details
    </button>
    <a href="{{ $googleOAuthConnected ? route('integrations.google.redirect') : route('domains.index') }}" class="figma-platform-menu-item">
        Edit Connection
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-tracking' })">
        Copy Tracking Link
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'open-pixel-guard' })">
        Open Pixel Guard
    </button>
    <a href="{{ route('paid-marketing.detection-settings', $menuDomain ? ['domain_id' => $menuDomain->id] : []) }}" class="figma-platform-menu-item">
        Open Audience Exclusion
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'manage-ad-account' })">
        Manage Ad Account
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'test-google' })">
        Test Connection
    </button>
    @if ($canDisconnect)
        <button type="button" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left" @click="$dispatch('platform-menu', { action: 'disconnect-google' })">
            Disconnect Platform
        </button>
    @else
        <span class="figma-platform-menu-item figma-platform-menu-item--muted block cursor-default">Disconnect Platform</span>
    @endif
</x-integrations.platform-card-dropdown>
