<article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px]">
    <h2 class="text-[16px] font-semibold text-white">Organization</h2>
    <p class="mt-[4px] text-[12px] text-[#a9a9a9]">Company details shown on reports and account context.</p>

    <form method="post" action="{{ route('profile.update') }}" class="mt-[16px] space-y-[14px]">
        @csrf
        @method('patch')

        <div>
            <label for="company_name" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Company / organization</label>
            <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $user->company_name) }}" maxlength="255" autocomplete="organization"
                class="figma-input" placeholder="Your company name">
            @foreach ($errors->get('company_name') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="website_url" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Website</label>
            <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $user->website_url) }}" maxlength="255" autocomplete="url"
                class="figma-input" placeholder="https://yourwebsite.com">
            @foreach ($errors->get('website_url') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="company_address" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Business address</label>
            <textarea id="company_address" name="company_address" rows="2" maxlength="500" class="figma-input" placeholder="Street, city, country">{{ old('company_address', $user->company_address) }}</textarea>
            @foreach ($errors->get('company_address') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div>
            <label for="support_email" class="mb-[6px] block text-[12px] font-semibold text-[#a9a9a9]">Support email</label>
            <input id="support_email" name="support_email" type="email" value="{{ old('support_email', $user->support_email) }}" maxlength="255" autocomplete="email"
                class="figma-input" placeholder="support@yourcompany.com">
            @foreach ($errors->get('support_email') as $message)
                <p class="mt-[6px] text-[12px] text-rose-300">{{ $message }}</p>
            @endforeach
        </div>

        <div class="rounded-[6px] border border-white/10 bg-black/25 px-[12px] py-[10px] text-[12px] text-white/70">
            Verification status:
            <span class="font-semibold {{ $user->email_verified_at ? 'text-emerald-300' : 'text-amber-200' }}">
                {{ $user->email_verified_at ? 'Email verified' : 'Email pending verification' }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-[12px] pt-[4px]">
            <button type="submit" class="rounded-[6px] bg-[#6400B2] px-[18px] py-[9px] text-[13px] font-semibold text-white hover:bg-[#7a1acc]">Save organization</button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)" class="text-[12px] text-emerald-300">Saved.</p>
            @endif
        </div>
    </form>
</article>
