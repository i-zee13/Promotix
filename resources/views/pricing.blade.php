@extends('layouts.auth')

@section('content')
<div class="min-h-screen bg-[#0D0D0D] px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl"
        x-data="{
            interval: 'monthly',
            yearlyDiscount: 0.15,
            priceLabel(row) {
                const monthlyCents = row.monthlyCents;
                const yearlyCents = row.yearlyCents;
                if (this.interval === 'yearly') {
                    if (yearlyCents > 0) {
                        const perMonth = yearlyCents / 12 / 100;
                        return '$' + perMonth.toFixed(0) + '/m';
                    }
                    const monthly = monthlyCents / 100;
                    const discounted = monthly * (1 - this.yearlyDiscount);
                    return '$' + discounted.toFixed(0) + '/m';
                }
                return '$' + (monthlyCents / 100).toFixed(0) + '/m';
            }
        }">
        <div class="mb-10 flex justify-center">
            <x-brand variant="dark" :height="44" />
        </div>

        <div class="text-center">
            <h1 class="text-3xl font-bold text-white sm:text-4xl">Plans &amp; pricing</h1>
            <p class="mx-auto mt-3 max-w-xl text-sm text-white/70">
                Same protection tiers as in the product. Sign up to start your trial.
            </p>

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
        </div>

        <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $limits = $plan->feature_limits ?? [];
                    $flags = $plan->feature_flags ?? [];
                    $domainLimit = $limits['domain_limit'] ?? null;
                    $visits = $limits['visits_limit'] ?? null;
                    $desc = $plan->short_description;
                    if (! $desc) {
                        $desc = match ($plan->slug) {
                            'starter' => 'Perfect for small websites starting with ad and bot protection.',
                            'pro' => 'Best for growing businesses managing multiple websites.',
                            'advanced' => 'For high-traffic businesses needing premium support.',
                            default => '',
                        };
                    }
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
                    $yearlyCents = (int) ($plan->price_yearly_cents ?? 0);
                @endphp
                <div class="relative">
                    <div class="absolute left-1/2 top-0 z-10 -translate-x-1/2 -translate-y-1/2">
                        <div @class([
                            'flex h-24 w-24 items-center justify-center rounded-full border text-base font-semibold shadow-[0_8px_24px_-12px_rgba(0,0,0,0.6)]',
                            'border-white/20 bg-[#B79CCB]/85 text-[#3A0D63]' => ! $plan->is_highlighted,
                            'border-amber-300/50 bg-amber-400/90 text-[#3A0D63]' => $plan->is_highlighted,
                        ])>
                            {{ $plan->name }}
                        </div>
                    </div>

                    <div class="relative h-full rounded-[15px] border border-white/35 bg-[#6400B3] px-6 pb-8 pt-16 shadow-[0_25px_60px_-20px_rgba(100,0,179,0.55)]">
                        @if ($desc)
                            <p class="mt-2 text-center text-sm text-white/85">{{ $desc }}</p>
                        @endif

                        <p class="mt-5 text-center text-3xl font-bold text-white"
                            x-text="priceLabel({ monthlyCents: {{ (int) $plan->price_cents }}, yearlyCents: {{ $yearlyCents }} })"></p>

                        <div class="mt-5 flex flex-col gap-2">
                            <a href="{{ route('register') }}"
                                class="block w-full rounded-[10px] bg-white py-2.5 text-center text-sm font-semibold text-[#6400B3] transition hover:bg-white/90">
                                {{ $plan->cta_label ?: 'Get started' }}
                            </a>
                            <a href="{{ route('login') }}" class="text-center text-xs font-medium text-white/80 underline-offset-4 hover:underline">Already have an account?</a>
                        </div>

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

        @if ($plans->isEmpty())
            <p class="mt-12 text-center text-sm text-white/60">Pricing is being configured. Please check back soon.</p>
        @endif
    </div>
</div>
@endsection
