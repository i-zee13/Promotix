@props([
    'menuId' => 'google',
    'googleOAuthConnected' => false,
    'menuDomain' => null,
    'primaryConnection' => null,
])

@php
    $canDisconnect = (bool) $primaryConnection;
    $editConnectionUrl = $googleOAuthConnected
        ? route('integrations.google.redirect')
        : route('domains.index', ['add' => 1]);
@endphp

<x-integrations.platform-card-dropdown :menu-id="$menuId" label="Google platform options">
    <a href="#connected-platforms" class="figma-platform-menu-item" title="Scroll to Connected Platforms table on this page">
        View Platform Details
    </a>
    <a href="{{ $editConnectionUrl }}" class="figma-platform-menu-item" title="{{ $googleOAuthConnected ? 'Reconnect or add another Google account' : 'Add a domain and connect Google' }}">
        Edit Connection
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-tracking' })" title="Copy Promotix tag script URL to clipboard">
        Copy Tracking Link
    </button>
    <a href="#connected-platforms" class="figma-platform-menu-item" @click.prevent="$dispatch('platform-menu', { action: 'open-pixel-guard' })" title="Scroll to connected platforms">
        Open Pixel Guard
    </a>
    <a href="#connected-platforms" class="figma-platform-menu-item" @click.prevent="$dispatch('platform-menu', { action: 'open-audience-exclusion' })" title="Set Up Audience Exclusion (Conversion ID / Label)">
        Open Audience Exclusion
    </a>
    <a href="#connected-platforms" class="figma-platform-menu-item" @click.prevent="$dispatch('platform-menu', { action: 'manage-ad-account' })" title="Scroll to connected platforms">
        Manage Ad Account
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'test-google' })" title="Check Google OAuth and synced accounts">
        Test Connection
    </button>
    @if ($canDisconnect)
        <button type="button" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left" @click="$dispatch('platform-menu', { action: 'disconnect-google' })" title="Remove Google OAuth from this workspace">
            Disconnect Platform
        </button>
    @else
        <span class="figma-platform-menu-item figma-platform-menu-item--muted block cursor-default" title="Connect Google first">Disconnect Platform</span>
    @endif
</x-integrations.platform-card-dropdown>
