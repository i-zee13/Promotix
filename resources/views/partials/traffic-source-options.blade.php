{{-- Traffic Source options: Google always; Meta/Microsoft only when Super Admin enables them. --}}
@php
    $adPlatforms = $enabledAdPlatforms ?? \App\Support\AdminIntegrationCatalog::enabledAdPlatforms();
@endphp
<option value="google_ads">Google Ads</option>
@if (! empty($adPlatforms['meta']))
    <option value="meta_ads">Meta Ads</option>
@endif
@if (! empty($adPlatforms['microsoft']))
    <option value="microsoft_ads">Microsoft Ads</option>
@endif
