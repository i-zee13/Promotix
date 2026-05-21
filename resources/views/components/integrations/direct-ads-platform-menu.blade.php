<x-integrations.platform-card-dropdown label="Direct Ads platform options">
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'direct-details' })">
        View Platform Details
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'edit-direct-id' })">
        Edit ID Tracking
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-direct-id' })">
        Copy Tracking ID
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'copy-tracking' })">
        Copy Tracking Link
    </button>
    <a href="{{ route('paid-marketing.dashboard') }}" class="figma-platform-menu-item">
        Open Campaign Links
    </a>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'test-direct' })">
        Test Tracking
    </button>
    <button type="button" class="figma-platform-menu-item w-full text-left" @click="$dispatch('platform-menu', { action: 'regenerate-direct-id' })">
        Regenerate ID
    </button>
    <button type="button" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left" @click="$dispatch('platform-menu', { action: 'remove-direct' })">
        Remove Platform
    </button>
</x-integrations.platform-card-dropdown>
