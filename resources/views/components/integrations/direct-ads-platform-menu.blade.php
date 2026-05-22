@props(['menuId' => 'direct'])

<x-integrations.platform-card-dropdown :menu-id="$menuId" label="Direct Ads platform options">
    <a href="#connected-platforms" class="figma-platform-menu-item" title="Scroll to Connected Platforms table on this page">
        View Platform Details
    </a>
    <a href="#direct-account-id" class="figma-platform-menu-item" @click.prevent="$dispatch('platform-menu', { action: 'edit-direct-id' })" title="Focus Customer ID field on this card">
        Edit ID Tracking
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-direct-id' })" title="Copy saved Customer ID to clipboard">
        Copy Tracking ID
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-tracking' })" title="Copy Promotix tag script URL to clipboard">
        Copy Tracking Link
    </button>
    <a href="{{ route('paid-marketing.dashboard') }}" class="figma-platform-menu-item" title="Paid Advertising dashboard and campaigns">
        Open Campaign Links
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'test-direct' })" title="Verify Direct Ads integration status">
        Test Tracking
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'regenerate-direct-id' })" title="Generate a new conversion tag ID in the form">
        Regenerate ID
    </button>
    <button type="button" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left" @click="$dispatch('platform-menu', { action: 'remove-direct' })" title="Delete Direct Ads integration">
        Remove Platform
    </button>
</x-integrations.platform-card-dropdown>
