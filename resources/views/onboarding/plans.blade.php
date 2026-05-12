@extends('layouts.auth')

@section('content')
<div class="min-h-screen bg-[#0D0D0D] px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl"
        x-data="{
            interval: 'monthly',
            yearlyDiscount: 0.15,
            priceLabel(cents) {
                const monthly = cents / 100;
                if (this.interval === 'yearly') {
                    const discounted = monthly * (1 - this.yearlyDiscount);
                    return '$' + (discounted).toFixed(0) + '/m';
                }
                return '$' + monthly.toFixed(0) + '/m';
            }
        }">
        {{-- Header --}}
        <div class="text-center">
            <h1 class="text-3xl font-bold text-white sm:text-4xl">
                Select your protection plan
            </h1>
            <p class="mt-2 text-lg text-white/85 sm:text-xl">
                and start your {{ $trialDays }}-day free trial
            </p>
            <p class="mx-auto mt-4 max-w-xl text-sm text-white/70">
                With 2M+ protected campaigns, Promotix has a plan for every account size,
                choose the one that fits you best.
            </p>

            {{-- Monthly / Yearly toggle --}}
            <div class="mt-6 flex items-center justify-center gap-3 text-sm font-medium text-white">
                <span :class="interval === 'monthly' ? 'text-white' : 'text-white/55'">Monthly</span>
                <button type="button" @click="interval = (interval === 'monthly' ? 'yearly' : 'monthly')"
                    class="relative inline-flex h-7 w-12 items-center rounded-full bg-[#6400B3] transition focus:outline-none focus:ring-2 focus:ring-white/40">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white transition"
                        :class="interval === 'yearly' ? 'translate-x-6' : 'translate-x-1'"></span>
                </button>
                <span :class="interval === 'yearly' ? 'text-white' : 'text-white/55'">Yearly</span>
                <span class="ml-2 rounded-full border border-white/30 bg-[#6400B3]/60 px-3 py-0.5 text-xs font-semibold text-white">Save 15%</span>
            </div>

            {{-- Currency (display only) --}}
            <div class="mt-3 inline-flex items-center gap-1 text-sm text-white/70">
                $ USD
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
            </div>
        </div>

        {{-- Plan cards --}}
        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    /** @var \App\Models\Plan $plan */
                    $limits = $plan->feature_limits ?? [];
                    $flags = $plan->feature_flags ?? [];
                    $domainLimit = $limits['domain_limit'] ?? null;
                    $visits = $limits['visits_limit'] ?? null;

                    $featureList = collect([
                        'Ad protection for Google, Meta &amp; Microsoft' => $flags['ad_protection'] ?? false,
                        'Bot protection for WordPress' => $flags['bot_protection'] ?? false,
                        'Custom marketing optimization rules' => $flags['marketing_optimization'] ?? false,
                        'Pixel Guard Connector' => $flags['pixel_guard'] ?? false,
                        'Multiple domain setup' => $flags['multi_domain'] ?? false,
                        'Agency permissions' => $flags['agency_permissions'] ?? false,
                        'Account overview' => $flags['account_overview'] ?? false,
                        'White-label reporting' => $flags['white_label_reports'] ?? false,
                        'In-person onboarding session' => $flags['in_person_onboarding'] ?? false,
                        'Consent Mode &mdash; coming soon!' => true,
                    ])->filter()->keys()->all();
                @endphp

                <div class="relative">
                    {{-- Floating badge --}}
                    <div class="absolute left-1/2 top-0 z-10 -translate-x-1/2 -translate-y-1/2">
                        <div class="flex h-24 w-24 items-center justify-center rounded-full border border-white/20 bg-[#B79CCB]/85 text-base font-semibold text-[#3A0D63] shadow-[0_8px_24px_-12px_rgba(0,0,0,0.6)]">
                            {{ $plan->name }}
                        </div>
                    </div>

                    {{-- Card --}}
                    <div class="relative h-full rounded-[15px] border border-white/35 bg-[#6400B3] px-6 pb-8 pt-16 shadow-[0_25px_60px_-20px_rgba(100,0,179,0.55)]">
                        <p class="mt-2 text-center text-sm text-white/85">
                            @if ($plan->slug === 'starter')
                                Perfect for small websites starting with ad and bot protection.
                            @elseif ($plan->slug === 'pro')
                                Best for growing businesses managing multiple websites.
                            @else
                                For high-traffic businesses needing premium support.
                            @endif
                        </p>

                        <p class="mt-5 text-center text-3xl font-bold text-white" x-text="priceLabel({{ (int) $plan->price_cents }})"></p>

                        <form method="POST" action="{{ route('onboarding.start-trial') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="plan_slug" value="{{ $plan->slug }}">
                            <input type="hidden" name="billing_interval" :value="interval">
                            <button type="submit"
                                class="block w-full rounded-[10px] bg-white py-2.5 text-center text-sm font-semibold text-[#6400B3] transition hover:bg-white/90">
                                Start free trial
                            </button>
                        </form>

                        <div class="mt-5 text-center text-sm text-white/85">
                            @if ($domainLimit)
                                Protect up to {{ $domainLimit }} website{{ $domainLimit === 1 ? '' : 's' }}<br>
                            @endif
                            @if ($visits)
                                For sites with total traffic up to<br>
                                <span class="font-semibold text-white">{{ number_format($visits / 1000) }}k visits/month</span>
                            @endif
                        </div>

                        <div class="mt-6">
                            <span class="inline-block rounded-md border border-white/35 bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                                Plan includes:
                            </span>
                            <ul class="mt-3 space-y-1.5 text-sm text-white/90">
                                @foreach ($featureList as $line)
                                    <li class="leading-relaxed">{!! $line !!}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Footer --}}
        <div class="mt-12 text-center text-sm text-white/65">
            Logged in as
            <span class="font-semibold text-white">{{ $user->email }}</span>.
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="underline-offset-4 hover:underline">Sign out</button>
            </form>
        </div>
    </div>
</div>
@endsection
